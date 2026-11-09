<?php

namespace App\Inventory;

use App\Auth\Permission;
use App\Models\Branch;
use App\Models\Inventory\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Imports the clinic's product list from a CSV saved by Excel (docs/inventario.md).
 * Tolerant with what Excel in Spanish produces: ";" or "," separators, Windows
 * accents, "45.000" prices. Existing products (same code, or same name) are
 * updated; new ones are created with their initial stock in the chosen branch.
 * Each row is independent: a bad row is reported and the rest still import.
 */
final class ProductImport
{
    public const HEADERS = ['nombre', 'codigo', 'tipo', 'unidad', 'precio_venta', 'costo', 'iva', 'existencia_minima', 'existencia_inicial'];

    public const MAX_ROWS = 2000;

    /** Header spellings people use → our column. */
    private const ALIASES = [
        'nombre' => 'nombre', 'producto' => 'nombre', 'descripcion' => 'nombre',
        'codigo' => 'codigo', 'sku' => 'codigo', 'referencia' => 'codigo',
        'tipo' => 'tipo', 'categoria' => 'tipo',
        'unidad' => 'unidad', 'presentacion' => 'unidad',
        'precio_venta' => 'precio_venta', 'precio' => 'precio_venta', 'valor' => 'precio_venta', 'precio_de_venta' => 'precio_venta',
        'costo' => 'costo', 'precio_costo' => 'costo', 'costo_unitario' => 'costo',
        'iva' => 'iva', 'impuesto' => 'iva',
        'existencia_minima' => 'existencia_minima', 'minimo' => 'existencia_minima', 'stock_minimo' => 'existencia_minima',
        'existencia_inicial' => 'existencia_inicial', 'existencia' => 'existencia_inicial', 'cantidad' => 'existencia_inicial', 'stock' => 'existencia_inicial',
    ];

    private const KINDS = [
        'producto' => 'product', 'product' => 'product',
        'medicamento' => 'medication', 'medicamentos' => 'medication', 'medication' => 'medication',
        'vacuna' => 'vaccine', 'vacunas' => 'vaccine', 'vaccine' => 'vaccine',
        'insumo' => 'supply', 'insumos' => 'supply', 'supply' => 'supply',
    ];

    private const UNITS = [
        'unidad' => 'unit', 'und' => 'unit', 'un' => 'unit', 'unit' => 'unit',
        'caja' => 'box', 'box' => 'box',
        'ml' => 'ml', 'mililitro' => 'ml', 'mililitros' => 'ml',
        'tableta' => 'tablet', 'tabletas' => 'tablet', 'tab' => 'tablet', 'tablet' => 'tablet',
        'dosis' => 'dose', 'dose' => 'dose',
        'g' => 'g', 'gramo' => 'g', 'gramos' => 'g',
        'kg' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg',
        'frasco' => 'bottle', 'bottle' => 'bottle',
        'pipeta' => 'pipette', 'pipette' => 'pipette',
    ];

    public function __construct(private readonly Inventory $inventory) {}

    /**
     * @return array{created: int, updated: int, errors: list<array{line: int, name: string|null, message: string}>}
     */
    public function __invoke(string $path, Branch $branch, User $actor): array
    {
        $rows = $this->read($path);
        $canCost = $actor->hasPermissionTo(Permission::InventoryAdjust->value);
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        foreach ($rows as $line => $row) {
            try {
                $created = DB::transaction(fn () => $this->importRow($row, $branch, $actor, $canCost));
                $result[$created ? 'created' : 'updated']++;
            } catch (ValidationException $e) {
                $result['errors'][] = ['line' => $line, 'name' => $row['nombre'] ?? null, 'message' => collect($e->errors())->flatten()->first()];
            } catch (Throwable $e) {
                report($e);
                $result['errors'][] = ['line' => $line, 'name' => $row['nombre'] ?? null, 'message' => 'No se pudo guardar esta fila.'];
            }
        }

        return $result;
    }

    /** @param array<string, string> $row */
    private function importRow(array $row, Branch $branch, User $actor, bool $canCost): bool
    {
        $name = trim($row['nombre'] ?? '');
        if ($name === '') {
            throw ValidationException::withMessages(['nombre' => 'Falta el nombre del producto.']);
        }

        $sku = ($code = strtoupper(trim($row['codigo'] ?? ''))) === '' ? null : $code;
        $kind = $this->mapped($row['tipo'] ?? '', self::KINDS, 'product', 'Tipo no reconocido (usa producto, medicamento, vacuna o insumo).');
        $unit = $this->mapped($row['unidad'] ?? '', self::UNITS, 'unit', 'Unidad no reconocida (por ejemplo: unidad, caja, ml, tableta, dosis, frasco, pipeta).');
        $price = $this->number($row['precio_venta'] ?? '', 'precio de venta') ?? 0;
        $cost = $this->number($row['costo'] ?? '', 'costo');
        $tax = $this->number($row['iva'] ?? '', 'IVA') ?? 0;
        $min = $this->number($row['existencia_minima'] ?? '', 'existencia mínima') ?? 0;
        $initial = $this->number($row['existencia_inicial'] ?? '', 'existencia inicial');

        if (! in_array((int) $tax, [0, 5, 19], true)) {
            throw ValidationException::withMessages(['iva' => 'El IVA debe ser 0, 5 o 19.']);
        }

        $existing = ($sku ? Product::where('sku', $sku)->first() : null)
            ?? Product::where('name_key', Product::nameKey($name))->first();

        $attributes = [
            'name' => Str::limit($name, 150, ''),
            'sku' => $sku ?? $existing?->sku,
            'kind' => $kind,
            'unit' => $unit,
            'sale_price' => $price,
            'tax_rate' => $tax,
            'min_stock' => $min,
            'is_active' => true,
        ];
        if ($canCost && $cost !== null) {
            $attributes['cost_price'] = $cost;
        }

        if ($existing !== null) {
            // Prices and data are updated; stock only moves with real movements (entrada / ajuste).
            $existing->update($attributes);

            return false;
        }

        $product = Product::create([...$attributes, 'tracks_stock' => true]);
        if ($initial !== null && $initial > 0) {
            $this->inventory->initial($product, $branch, (string) $initial, $actor, ['unit_cost' => $canCost ? $cost : null, 'note' => 'Importación']);
        }

        return true;
    }

    /**
     * Rows keyed by our column names, with their line number in the file.
     *
     * @return array<int, array<string, string>>
     */
    private function read(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // Excel on Windows
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = array_map(fn ($h) => self::ALIASES[$this->key((string) $h)] ?? null, fgetcsv($stream, null, $delimiter, '"', '') ?: []);
        if (! in_array('nombre', $header, true)) {
            throw ValidationException::withMessages(['file' => 'El archivo no tiene la columna "nombre". Descarga la plantilla y úsala como guía.']);
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($cells === [null] || trim(implode('', $cells)) === '') {
                continue; // empty line
            }
            if (count($rows) >= self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'El archivo tiene más de '.self::MAX_ROWS.' productos. Divídelo en varios archivos.']);
            }
            $row = [];
            foreach ($header as $i => $column) {
                if ($column !== null) {
                    $row[$column] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $rows[$line] = $row;
        }
        fclose($stream);

        return $rows;
    }

    /** "Existencia mínima" → "existencia_minima". */
    private function key(string $header): string
    {
        return (string) Str::of($header)->ascii()->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    /** @param array<string, string> $map */
    private function mapped(string $value, array $map, string $default, string $error): string
    {
        $key = $this->key($value);
        if ($key === '') {
            return $default;
        }

        return $map[$key] ?? throw ValidationException::withMessages(['value' => $error." Llegó: «{$value}»."]);
    }

    /** "45.000", "$ 45.000", "45000", "12,5" → number; empty → null. */
    private function number(string $value, string $label): ?float
    {
        $v = str_replace(['$', ' ', "\u{00A0}"], '', trim($value));
        if ($v === '') {
            return null;
        }
        if (str_contains($v, '.') && str_contains($v, ',')) {
            $v = str_replace('.', '', $v);              // 45.000,50
            $v = str_replace(',', '.', $v);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $v)) {
            $v = str_replace('.', '', $v);              // 45.000 (thousands)
        } else {
            $v = str_replace(',', '.', $v);             // 12,5 (decimal comma)
        }

        if (! is_numeric($v) || (float) $v < 0) {
            throw ValidationException::withMessages(['value' => "El valor de {$label} no es un número válido: «{$value}»."]);
        }

        return (float) $v;
    }
}
