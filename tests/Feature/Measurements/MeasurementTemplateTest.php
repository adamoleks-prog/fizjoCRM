<?php

use App\Enums\MeasurementType;
use App\Models\MeasurementTemplate;
use App\Models\User;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
});

function makeTemplate(User $operator, array $attributes = []): MeasurementTemplate
{
    $template = new MeasurementTemplate(array_merge([
        'name' => 'Zakres zgięcia kolana',
        'type' => MeasurementType::Bilateral,
        'unit' => '°',
    ], $attributes));
    $template->operator_id = $operator->id;
    $template->save();

    return $template;
}

it('creates a measurement template', function () {
    $this->actingAs($this->operator)
        ->post(route('measurements.store'), [
            'name' => 'Obwód uda',
            'description' => 'Pomiar 10 cm nad rzepką',
            'type' => 'numeric',
            'unit' => 'cm',
        ])
        ->assertRedirect(route('measurements.index'));

    $template = MeasurementTemplate::sole();

    expect($template->name)->toBe('Obwód uda')
        ->and($template->type)->toBe(MeasurementType::Numeric)
        ->and($template->unit)->toBe('cm')
        ->and($template->operator_id)->toBe($this->operator->id);
});

it('drops the unit for types that do not use one', function () {
    $this->actingAs($this->operator)
        ->post(route('measurements.store'), [
            'name' => 'Objaw Lasègue’a',
            'type' => 'boolean',
            'unit' => 'cm',
        ]);

    expect(MeasurementTemplate::sole()->unit)->toBeNull();
});

it('requires a name and a valid type', function () {
    $this->actingAs($this->operator)
        ->post(route('measurements.store'), ['name' => '', 'type' => 'nieistniejacy'])
        ->assertSessionHasErrors(['name', 'type']);
});

it('lists only the own templates', function () {
    makeTemplate($this->operator);
    makeTemplate(User::factory()->operator()->create(), ['name' => 'Cudzy pomiar']);

    $this->actingAs($this->operator)
        ->get(route('measurements.index'))
        ->assertOk()
        ->assertSee('Zakres zgięcia kolana')
        ->assertDontSee('Cudzy pomiar');
});

it('forbids deleting a template of another operator', function () {
    $foreign = makeTemplate(User::factory()->operator()->create());

    $this->actingAs($this->operator)
        ->delete(route('measurements.destroy', $foreign))
        ->assertNotFound();

    expect(MeasurementTemplate::withoutGlobalScopes()->count())->toBe(1);
});

it('soft deletes a template so recorded results keep their label', function () {
    $template = makeTemplate($this->operator);

    $this->actingAs($this->operator)
        ->delete(route('measurements.destroy', $template))
        ->assertRedirect(route('measurements.index'));

    expect(MeasurementTemplate::count())->toBe(0)
        ->and(MeasurementTemplate::withTrashed()->count())->toBe(1);
});
