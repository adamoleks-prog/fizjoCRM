<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The kinds of visit offered by the practice. Services are switched off rather
 * than deleted, so past visits keep their label.
 */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.services', [
            'services' => Service::orderBy('sort')->orderBy('name')->get(),
            'editing' => $request->filled('edit') ? Service::findOrFail($request->integer('edit')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Service::create([...$this->validated($request), 'sort' => (int) Service::max('sort') + 1]);

        return redirect()->route('admin.services.index')->with('status', 'Dodano rodzaj wizyty.');
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $this->validated($request);

        // The default service is what older visits fall back to — it stays on.
        if ($service->is_default) {
            $data['active'] = true;
        }

        $service->update($data);

        return redirect()->route('admin.services.index')->with('status', 'Zapisano rodzaj wizyty.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
        ]);

        return [
            ...$data,
            'online_bookable' => $request->boolean('online_bookable'),
            'active' => $request->boolean('active', true),
        ];
    }
}
