<?php

use App\Enums\AppointmentStatus;
use App\Enums\SecurityEventType;
use App\Models\Appointment;
use App\Models\Document;
use App\Models\Measurement;
use App\Models\MeasurementTemplate;
use App\Models\Patient;
use App\Models\PatientComorbidity;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Encryption\PatientCipher;
use App\Services\Encryption\PatientKeyring;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Queue::fake();
    Storage::fake('patient_documents');
    $this->operator = User::factory()->operator()->create();
    $this->patient = Patient::factory()->forOperator($this->operator)->create([
        'notes' => 'Uczulenie na lateks',
        'address' => 'ul. Długa 5, Kraków',
    ]);
});

/** Forget keys and owners remembered in this "request". */
function freshKeyring(): void
{
    app()->forgetScopedInstances();
}

test('clinical text is stored encrypted and read back as plain text', function () {
    $visit = Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id,
        'interview' => 'Ból lędźwi od trzech tygodni',
        'icd10_code' => 'M54.5',
        'booking_details' => ['reason' => 'Ból kolana'],
    ]);

    $raw = DB::table('appointments')->find($visit->id);
    expect($raw->interview)->toStartWith(PatientCipher::PREFIX)->not->toContain('lędźwi')
        ->and($raw->icd10_code)->toStartWith(PatientCipher::PREFIX)
        ->and($raw->booking_details)->not->toContain('kolana');

    freshKeyring();
    $loaded = Appointment::withoutGlobalScopes()->find($visit->id);
    expect($loaded->interview)->toBe('Ból lędźwi od trzech tygodni')
        ->and($loaded->icd10_code)->toBe('M54.5')
        ->and($loaded->reportedProblem())->toBe('Ból kolana');
});

test('a new patient\'s notes never reach the database unencrypted', function () {
    $raw = DB::table('patients')->find($this->patient->id);

    expect($raw->notes)->toStartWith(PatientCipher::PREFIX)
        ->and($raw->address)->toStartWith(PatientCipher::PREFIX)
        ->and($this->patient->notes)->toBe('Uczulenie na lateks')
        ->and(Patient::withoutGlobalScopes()->find($this->patient->id)->address)->toBe('ul. Długa 5, Kraków');
});

test('each patient has an own key', function () {
    $other = Patient::factory()->forOperator($this->operator)->create(['notes' => 'Uczulenie na lateks']);

    $a = DB::table('patient_keys')->where('patient_id', $this->patient->id)->value('encrypted_key');
    $b = DB::table('patient_keys')->where('patient_id', $other->id)->value('encrypted_key');

    expect($a)->not->toBeNull()->not->toBe($b);

    // One patient's ciphertext cannot be read with another patient's key.
    $stolen = DB::table('patients')->where('id', $this->patient->id)->value('notes');
    expect(fn () => app(PatientKeyring::class)->for($other->id)->decryptString(substr($stolen, strlen(PatientCipher::PREFIX))))
        ->toThrow(DecryptException::class);
});

test('editing a field keeps it encrypted and leaves other fields alone', function () {
    $visit = Appointment::factory()->forOperator($this->operator)->create(['patient_id' => $this->patient->id, 'interview' => 'A', 'conclusions' => 'B']);
    $before = DB::table('appointments')->where('id', $visit->id)->value('conclusions');

    $visit->update(['interview' => 'Nowy wywiad']);

    expect(DB::table('appointments')->where('id', $visit->id)->value('conclusions'))->toBe($before)
        ->and(Appointment::withoutGlobalScopes()->find($visit->id)->interview)->toBe('Nowy wywiad');
});

test('child records (measurements) are encrypted with the patient\'s key', function () {
    $visit = Appointment::factory()->forOperator($this->operator)->create(['patient_id' => $this->patient->id]);
    $template = new MeasurementTemplate(['name' => 'Zgięcie', 'type' => 'numeric', 'unit' => '°']);
    $template->operator_id = $this->operator->id;
    $template->save();
    $measurement = new Measurement(['value_left' => 90, 'note' => 'Ból przy końcu zakresu']);
    $measurement->operator_id = $this->operator->id;
    $measurement->appointment_id = $visit->id;
    $measurement->measurement_template_id = $template->id;
    $measurement->save();

    expect(DB::table('measurements')->value('note'))->toStartWith(PatientCipher::PREFIX);
    freshKeyring();
    expect(Measurement::withoutGlobalScopes()->first()->note)->toBe('Ból przy końcu zakresu');
});

test('moving a visit to another patient re-encrypts it with the new key', function () {
    $visit = Appointment::factory()->forOperator($this->operator)->create(['patient_id' => $this->patient->id, 'interview' => 'Wywiad']);
    $other = Patient::factory()->forOperator($this->operator)->create();

    $visit->patient_id = $other->id;
    $visit->save();

    freshKeyring();
    expect(Appointment::withoutGlobalScopes()->find($visit->id)->interview)->toBe('Wywiad');
});

test('uploaded PDFs are stored encrypted and downloaded decrypted', function () {
    $pdf = "%PDF-1.4\nDane pacjenta Kowalski\n%%EOF";

    $this->actingAs($this->operator)->post(route('documents.store', $this->patient), [
        'file' => UploadedFile::fake()->createWithContent('wynik-kowalski.pdf', $pdf),
        'type' => 'other',
    ])->assertSessionHasNoErrors();

    $document = Document::sole();
    $stored = Storage::disk('patient_documents')->get($document->disk_path);
    expect($stored)->toStartWith(PatientCipher::FILE_MAGIC)->not->toContain('Kowalski')
        ->and(DB::table('documents')->value('original_filename'))->not->toContain('kowalski');

    $this->actingAs($this->operator)->get(route('documents.show', $document))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($this->actingAs($this->operator)->get(route('documents.show', $document))->getContent())->toBe($pdf);
});

test('data written before encryption is still readable and gets encrypted by the command', function () {
    $visit = Appointment::factory()->forOperator($this->operator)->create(['patient_id' => $this->patient->id]);
    DB::table('appointments')->where('id', $visit->id)->update(['interview' => 'Stary wywiad', 'booking_details' => '{"reason":"Stary problem"}']);
    DB::table('patients')->where('id', $this->patient->id)->update(['notes' => 'Stara notatka']);
    Storage::disk('patient_documents')->put('patients/old.pdf', '%PDF-stary');
    $document = new Document(['disk_path' => 'patients/old.pdf', 'original_filename' => 'stary.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10, 'uploaded_by_user_id' => $this->operator->id]);
    $document->patient_id = $this->patient->id;
    $document->save();
    DB::table('documents')->where('id', $document->id)->update(['original_filename' => 'stary.pdf']);

    freshKeyring();
    expect(Appointment::withoutGlobalScopes()->find($visit->id)->interview)->toBe('Stary wywiad');

    $this->artisan('patient-data:encrypt')->assertSuccessful();
    $this->artisan('patient-data:encrypt')->assertSuccessful(); // again — nothing breaks

    expect(DB::table('appointments')->where('id', $visit->id)->value('interview'))->toStartWith(PatientCipher::PREFIX)
        ->and(DB::table('patients')->where('id', $this->patient->id)->value('notes'))->toStartWith(PatientCipher::PREFIX)
        ->and(DB::table('documents')->where('id', $document->id)->value('original_filename'))->toStartWith(PatientCipher::PREFIX)
        ->and(Storage::disk('patient_documents')->get('patients/old.pdf'))->toStartWith(PatientCipher::FILE_MAGIC);

    freshKeyring();
    $loaded = Appointment::withoutGlobalScopes()->find($visit->id);
    expect($loaded->interview)->toBe('Stary wywiad')
        ->and($loaded->reportedProblem())->toBe('Stary problem')
        ->and(Patient::withoutGlobalScopes()->find($this->patient->id)->notes)->toBe('Stara notatka');
});

test('erasing a patient destroys the key, the files and the identity', function () {
    $admin = User::factory()->admin()->create();
    Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id, 'interview' => 'Wywiad', 'status' => AppointmentStatus::Completed,
        'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHour(),
    ]);
    $comorbidity = new PatientComorbidity(['name' => 'Cukrzyca', 'kind' => 'chronic']);
    $comorbidity->operator_id = $this->operator->id;
    $comorbidity->patient_id = $this->patient->id;
    $comorbidity->save();
    $this->actingAs($this->operator)->post(route('documents.store', $this->patient), [
        'file' => UploadedFile::fake()->createWithContent('a.pdf', '%PDF-x'), 'type' => 'other',
    ]);
    $path = Document::sole()->disk_path;

    $this->actingAs($admin)->post(route('admin.patients.erase', $this->patient), ['confirmation' => 'USUŃ', 'reason_ok' => '1'])
        ->assertRedirect(route('patients.index'));

    expect(DB::table('patient_keys')->where('patient_id', $this->patient->id)->exists())->toBeFalse()
        ->and(Storage::disk('patient_documents')->exists($path))->toBeFalse();

    $raw = DB::table('patients')->find($this->patient->id);
    expect($raw->last_name)->toBe('pacjent #'.$this->patient->id)
        ->and($raw->phone)->toBeNull()->and($raw->notes)->toBeNull()->and($raw->erased_at)->not->toBeNull();

    freshKeyring();
    expect(Appointment::withoutGlobalScopes()->where('patient_id', $this->patient->id)->first()->interview)->toBeNull()
        ->and(PatientComorbidity::withoutGlobalScopes()->first()->name)->toBeNull();
    expect(SecurityEvent::where('type', SecurityEventType::PatientErased)->exists())->toBeTrue();
});

test('erasure needs the typed confirmation, an administrator and no upcoming visits', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($this->operator)->post(route('admin.patients.erase', $this->patient), ['confirmation' => 'USUŃ', 'reason_ok' => '1'])->assertForbidden();
    $this->actingAs($admin)->post(route('admin.patients.erase', $this->patient), ['confirmation' => 'usun', 'reason_ok' => '1'])->assertSessionHasErrors('confirmation');

    Appointment::factory()->forOperator($this->operator)->create([
        'patient_id' => $this->patient->id, 'status' => AppointmentStatus::Scheduled,
        'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(),
    ]);
    $this->actingAs($admin)->post(route('admin.patients.erase', $this->patient), ['confirmation' => 'USUŃ', 'reason_ok' => '1'])->assertSessionHasErrors('confirmation');

    expect(DB::table('patient_keys')->where('patient_id', $this->patient->id)->exists())->toBeTrue();
});
