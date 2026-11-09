<?php

namespace App\Models\Documents;

use App\Models\Concerns\StoresDatesInUtc;
use App\Tenancy\BelongsToClinic;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One signature on a document, with its evidence (who, what hash, when, from where). */
class PetDocumentSignature extends Model
{
    use BelongsToClinic, HasUuids, StoresDatesInUtc;

    protected $fillable = ['pet_document_id', 'role', 'signer_name', 'signer_document', 'signer_user_id', 'image_path', 'method', 'content_hash', 'ip', 'user_agent', 'collected_by', 'signed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }
}
