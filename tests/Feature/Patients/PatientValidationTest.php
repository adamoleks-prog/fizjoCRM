<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->operator = User::factory()->operator()->create();
    $this->valid = ['first_name' => 'Jan', 'last_name' => 'Kowalski', 'phone' => '602118940', 'email' => 'jan@example.com', 'reminders_enabled' => 1];
});

it('rejects digits, spaces and special characters in names', function (array $fields, string $error) {
    $this->actingAs($this->operator)
        ->post(route('patients.store'), array_merge($this->valid, $fields))
        ->assertSessionHasErrors($error);
})->with([
    'digits in first name' => [['first_name' => '123123'], 'first_name'],
    'space in first name' => [['first_name' => 'we wf'], 'first_name'],
    'digits in surname' => [['last_name' => '21212f'], 'last_name'],
    'special char in surname' => [['last_name' => 'Kowalski!'], 'last_name'],
    'bad e-mail without tld' => [['email' => 'jan@gmail'], 'email'],
    'bad e-mail double at' => [['email' => 'jan@@gmail.com'], 'email'],
    'phone too short' => [['phone' => '60211894'], 'phone'],
    'fake phone' => [['phone' => '111111111'], 'phone'],
]);

it('stores a valid patient with the phone as +48 xxx xxx xxx', function () {
    $this->actingAs($this->operator)
        ->post(route('patients.store'), array_merge($this->valid, ['last_name' => 'Nowak-Kowalska', 'first_name' => 'Żaneta']))
        ->assertSessionHasNoErrors();

    $patient = Patient::sole();

    expect($patient->phone)->toBe('+48 602 118 940')
        ->and($patient->last_name)->toBe('Nowak-Kowalska');
});

it('accepts a landline in the panel and phones typed in any common form', function (string $typed) {
    $this->actingAs($this->operator)
        ->post(route('patients.store'), array_merge($this->valid, ['phone' => $typed]))
        ->assertSessionHasNoErrors();
})->with(['226001122', '+48 602 118 940', '0048602118940', '602-118-940']);

it('lets an existing card with an old-style phone be saved again', function () {
    $patient = Patient::factory()->forOperator($this->operator)->create(['first_name' => 'Jan', 'last_name' => 'Kowalski']);
    DB::table('patients')->where('id', $patient->id)->update(['phone' => '602 118 940']);

    $this->actingAs($this->operator)->get(route('patients.edit', $patient))->assertSee('value="602118940"', false);

    $this->actingAs($this->operator)
        ->put(route('patients.update', $patient), array_merge($this->valid, ['phone' => '602118940']))
        ->assertSessionHasNoErrors();

    expect($patient->fresh()->phone)->toBe('+48 602 118 940');
});

it('filters forbidden characters while typing', function () {
    $this->actingAs($this->operator)->get(route('patients.create'))
        ->assertSee("replace(/[^\p{L}]/gu, '')", false)
        ->assertSee('+48');
});
