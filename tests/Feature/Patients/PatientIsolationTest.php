<?php

use App\Models\Patient;
use App\Models\User;

it('shows an operator only their own patients in the list', function () {
    $operator = User::factory()->operator()->create();
    $other = User::factory()->operator()->create();

    Patient::factory()->forOperator($operator)->create(['last_name' => 'Wlasny']);
    Patient::factory()->forOperator($other)->create(['last_name' => 'Obcy']);

    $response = $this->actingAs($operator)->get(route('patients.index'));

    $response->assertOk()
        ->assertSee('Wlasny')
        ->assertDontSee('Obcy');
});

it('blocks an operator from viewing another operators patient by direct id', function () {
    $operator = User::factory()->operator()->create();
    $foreignPatient = Patient::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($operator)
        ->get(route('patients.show', $foreignPatient))
        ->assertNotFound();
});

it('blocks an operator from updating another operators patient', function () {
    $operator = User::factory()->operator()->create();
    $foreignPatient = Patient::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($operator)
        ->put(route('patients.update', $foreignPatient), [
            'first_name' => 'Zmieniony',
            'last_name' => 'Pacjent',
        ])
        ->assertNotFound();

    expect($foreignPatient->fresh()->first_name)->not->toBe('Zmieniony');
});

it('blocks an operator from deleting another operators patient', function () {
    $operator = User::factory()->operator()->create();
    $foreignPatient = Patient::factory()->forOperator(User::factory()->operator()->create())->create();

    $this->actingAs($operator)
        ->delete(route('patients.destroy', $foreignPatient))
        ->assertNotFound();

    expect(Patient::withoutGlobalScopes()->find($foreignPatient->id))->not->toBeNull();
});

it('lets an admin see patients of every operator', function () {
    $admin = User::factory()->admin()->create();

    Patient::factory()->forOperator(User::factory()->operator()->create())->create(['last_name' => 'Pierwszy']);
    Patient::factory()->forOperator(User::factory()->operator()->create())->create(['last_name' => 'Drugi']);

    $this->actingAs($admin)
        ->get(route('patients.index'))
        ->assertOk()
        ->assertSee('Pierwszy')
        ->assertSee('Drugi');
});

it('scopes the patient query builder to the authenticated operator', function () {
    $operator = User::factory()->operator()->create();
    Patient::factory()->forOperator($operator)->count(2)->create();
    Patient::factory()->forOperator(User::factory()->operator()->create())->count(3)->create();

    $this->actingAs($operator);

    expect(Patient::count())->toBe(2);
});

it('requires authentication for patient routes', function () {
    $this->get(route('patients.index'))->assertRedirect(route('login'));
});
