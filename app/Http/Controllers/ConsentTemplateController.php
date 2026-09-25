<?php

namespace App\Http\Controllers;

use App\Models\ConsentTemplate;
use App\Services\Consents\DefaultConsentTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Each physiotherapist edits their own consent wordings. Editing does not touch
 * consents already signed — each signed PDF keeps the text it was signed with.
 */
class ConsentTemplateController extends Controller
{
    public function index(Request $request): View
    {
        DefaultConsentTemplates::ensureFor($request->user());

        return view('consents.templates', [
            'templates' => ConsentTemplate::query()->where('operator_id', $request->user()->id)->orderBy('name')->get(),
            'editing' => $request->filled('edit')
                ? ConsentTemplate::where('operator_id', $request->user()->id)->findOrFail($request->integer('edit'))
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $template = new ConsentTemplate($this->validated($request));
        $template->operator_id = $request->user()->id;
        $template->save();

        return redirect()->route('consent-templates.index')->with('status', 'Dodano wzór zgody.');
    }

    public function update(Request $request, ConsentTemplate $consentTemplate): RedirectResponse
    {
        $this->ensureOwn($request, $consentTemplate);

        $consentTemplate->update($this->validated($request));

        return redirect()->route('consent-templates.index')->with('status', 'Zapisano wzór zgody. Podpisane wcześniej zgody pozostają bez zmian.');
    }

    public function destroy(Request $request, ConsentTemplate $consentTemplate): RedirectResponse
    {
        $this->ensureOwn($request, $consentTemplate);

        $consentTemplate->delete();

        return redirect()->route('consent-templates.index')->with('status', 'Usunięto wzór zgody.');
    }

    /**
     * @return array{name: string, body: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
    }

    /** Templates are personal — an admin signs with their own wordings too. */
    private function ensureOwn(Request $request, ConsentTemplate $template): void
    {
        abort_unless($template->operator_id === $request->user()->id, 404);
    }
}
