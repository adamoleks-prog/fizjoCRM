<?php

use App\Models\User;
use Database\Seeders\Icd10CodeSeeder;

beforeEach(function () {
    $this->seed(Icd10CodeSeeder::class);
    $this->operator = User::factory()->operator()->create();
});

it('ranks an exact code match first', function () {
    $response = $this->actingAs($this->operator)->getJson(route('icd10.search', ['q' => 'M54']));

    expect($response->json('0.code'))->toBe('M54');
});

it('ranks a name starting with the term above a mid-word match', function () {
    // "rwa" wystepuje tez w srodku slowa "naderwanie" — rwa kulszowa musi byc wyzej.
    $response = $this->actingAs($this->operator)->getJson(route('icd10.search', ['q' => 'rwa']));

    expect($response->json('0.name'))->toContain('Rwa kulszowa');
});

it('ranks a whole-word match above a mid-word match', function () {
    $response = $this->actingAs($this->operator)->getJson(route('icd10.search', ['q' => 'kolanowego']));

    expect($response->json('0.name'))->toContain('stawu kolanowego');
});
