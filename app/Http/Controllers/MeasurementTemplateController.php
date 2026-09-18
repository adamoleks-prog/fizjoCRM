<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMeasurementTemplateRequest;
use App\Models\MeasurementTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MeasurementTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', MeasurementTemplate::class);

        return view('measurements.index', [
            'templates' => MeasurementTemplate::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreMeasurementTemplateRequest $request): RedirectResponse
    {
        $template = new MeasurementTemplate($request->safe()->all());
        $template->operator_id = $request->user()->id;

        if (! $template->type->usesUnit()) {
            $template->unit = null;
        }

        $template->save();

        return redirect()
            ->route('measurements.index')
            ->with('status', 'Pomiar został dodany.');
    }

    public function destroy(MeasurementTemplate $measurement): RedirectResponse
    {
        $this->authorize('delete', $measurement);

        // Soft deleted, so results already recorded on past visits keep their label.
        $measurement->delete();

        return redirect()
            ->route('measurements.index')
            ->with('status', 'Pomiar został usunięty z biblioteki. Zapisane wyniki pozostają w kartach wizyt.');
    }
}
