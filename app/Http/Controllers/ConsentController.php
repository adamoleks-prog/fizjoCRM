<?php

namespace App\Http\Controllers;

use App\Models\ConsentTemplate;
use App\Models\Patient;
use App\Models\User;
use App\Services\Consents\ConsentSigner;
use App\Services\Consents\DefaultConsentTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsentController extends Controller
{
    /**
     * The signing screen, meant to be handed to the patient on a tablet: no menu,
     * nothing but the text and the signature box.
     */
    public function create(Request $request, Patient $patient): View
    {
        $this->authorize('update', $patient);

        $physiotherapist = User::findOrFail($patient->operator_id);
        DefaultConsentTemplates::ensureFor($physiotherapist);

        $templates = ConsentTemplate::query()->where('operator_id', $physiotherapist->id)->orderBy('name')->get();
        $template = $templates->firstWhere('id', $request->integer('template')) ?? $templates->first();

        return view('consents.sign', [
            'patient' => $patient,
            'templates' => $templates,
            'template' => $template,
            'text' => $template?->render($patient, $physiotherapist),
        ]);
    }

    public function store(Request $request, Patient $patient, ConsentSigner $signer): RedirectResponse
    {
        $this->authorize('update', $patient);

        $data = $request->validate([
            'consent_template_id' => ['required', 'integer'],
            'signature' => ['required', 'string'],
            'read' => ['accepted'],
        ], [
            'read.accepted' => 'Zaznacz, że zapoznałeś(-aś) się z treścią.',
            'signature.required' => 'Podpisz się w ramce.',
        ]);

        $template = ConsentTemplate::query()
            ->where('operator_id', $patient->operator_id)
            ->findOrFail($data['consent_template_id']);

        $signer->sign($patient, $template, $data['signature'], $request->user(), $request);

        return redirect()
            ->route('patients.show', $patient)
            ->with('status', 'Zgoda „'.$template->name.'” została podpisana i zapisana w dokumentach pacjenta.');
    }
}
