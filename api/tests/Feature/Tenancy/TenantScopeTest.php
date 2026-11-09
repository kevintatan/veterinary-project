<?php

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\User;
use App\Tenancy\TenancyViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->clinicA = Clinic::factory()->create();
    $this->clinicB = Clinic::factory()->create();

    tenant()->runAs($this->clinicA->id, fn () => Branch::create(['name' => 'Sede A']));
    tenant()->runAs($this->clinicB->id, fn () => Branch::create(['name' => 'Sede B']));
});

it('fails closed when a tenant model is queried without a clinic in context', function () {
    tenant()->forget();

    Branch::all();
})->throws(TenancyViolation::class);

it('only returns rows of the clinic in context', function () {
    inClinic($this->clinicA);

    expect(Branch::pluck('name')->all())->toBe(['Sede A']);

    inClinic($this->clinicB);

    expect(Branch::pluck('name')->all())->toBe(['Sede B']);
});

it('cannot find a row of another clinic by id', function () {
    $branchB = tenant()->runAs($this->clinicB->id, fn () => Branch::first());

    inClinic($this->clinicA);

    expect(Branch::find($branchB->id))->toBeNull();
});

it('scopes mass updates and deletes to the clinic in context', function () {
    inClinic($this->clinicA);

    Branch::query()->update(['name' => 'Renombrada']);
    Branch::query()->delete();

    $namesInB = tenant()->runAs($this->clinicB->id, fn () => Branch::pluck('name')->all());
    expect($namesInB)->toBe(['Sede B']);
});

it('stamps clinic_id automatically on create', function () {
    inClinic($this->clinicA);

    $branch = Branch::create(['name' => 'Nueva']);

    expect($branch->clinic_id)->toBe($this->clinicA->id);
});

it('refuses to create a row for another clinic', function () {
    inClinic($this->clinicA);

    $branch = new Branch(['name' => 'Intrusa']);
    $branch->clinic_id = $this->clinicB->id;
    $branch->save();
})->throws(TenancyViolation::class);

it('refuses to move a row to another clinic', function () {
    inClinic($this->clinicA);

    $branch = Branch::first();
    $branch->clinic_id = $this->clinicB->id;
    $branch->save();
})->throws(TenancyViolation::class);

it('lets explicit platform bypass see every clinic', function () {
    tenant()->forget();

    $count = tenant()->withoutTenancy('test', fn () => Branch::count());

    expect($count)->toBe(2)
        ->and(tenant()->isBypassed())->toBeFalse();
});

it('restores the previous clinic after runAs', function () {
    inClinic($this->clinicA);

    tenant()->runAs($this->clinicB->id, fn () => null);

    expect(tenant()->id())->toBe($this->clinicA->id);
});

it('rejects cross-clinic references at the database level (composite FK)', function () {
    $branchB = tenant()->runAs($this->clinicB->id, fn () => Branch::first());
    $user = User::factory()->create();

    DB::table('clinic_user')->insert([
        'id' => (string) str()->uuid7(),
        'clinic_id' => $this->clinicA->id,
        'user_id' => $user->id,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Even bypassing Eloquent: branch of clinic B + membership of clinic A is impossible.
    DB::table('branch_user')->insert([
        'clinic_id' => $this->clinicA->id,
        'branch_id' => $branchB->id,
        'user_id' => $user->id,
    ]);
})->throws(QueryException::class);
