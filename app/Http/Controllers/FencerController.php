<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Fencer;
use App\Services\ZipGeocoder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FencerController extends Controller
{
    private const WEAPONS = ['foil', 'epee', 'sabre'];

    private const AGE_GROUPS = ['Y10', 'Y12', 'Y14', 'Cadet', 'Junior', 'Senior', 'Vet'];

    private const RATINGS = ['U', 'E', 'D', 'C', 'B', 'A'];

    public function create(): View
    {
        return view('fencers.form', $this->formData(new Fencer(['drive_radius_miles' => 450])));
    }

    public function store(Request $request, ZipGeocoder $geocoder): RedirectResponse
    {
        $data = $this->validated($request);
        [$weapons, $primary] = $this->weaponSelection($request);

        $fencer = new Fencer($data);
        $fencer->user_id = $request->user()->id;
        $fencer->weapon = $primary;
        $fencer->rating = $weapons[$primary];
        $this->applyGeocode($fencer, $geocoder);
        $fencer->save();
        $this->rebuildWeapons($fencer, $weapons, $primary);

        session(['active_fencer_id' => $fencer->id]);

        return redirect('/')->with('status', "Profile saved for {$fencer->name}.");
    }

    public function edit(Request $request, Fencer $fencer): View
    {
        $this->authorizeOwner($request, $fencer);

        return view('fencers.form', $this->formData($fencer->load('weapons')));
    }

    public function update(Request $request, Fencer $fencer, ZipGeocoder $geocoder): RedirectResponse
    {
        $this->authorizeOwner($request, $fencer);

        $data = $this->validated($request);
        [$weapons, $primary] = $this->weaponSelection($request);
        $zipChanged = ($data['home_zip'] ?? null) !== $fencer->home_zip;

        $fencer->fill($data);
        $fencer->weapon = $primary;
        $fencer->rating = $weapons[$primary];
        if ($zipChanged) {
            $this->applyGeocode($fencer, $geocoder);
        }
        $fencer->save();
        $this->rebuildWeapons($fencer, $weapons, $primary);

        session(['active_fencer_id' => $fencer->id]);

        return redirect('/')->with('status', "Profile updated for {$fencer->name}.");
    }

    public function select(Request $request, Fencer $fencer): RedirectResponse
    {
        $this->authorizeOwner($request, $fencer);
        session(['active_fencer_id' => $fencer->id]);

        return redirect('/');
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function authorizeOwner(Request $request, Fencer $fencer): void
    {
        abort_unless($fencer->user_id === $request->user()->id, 403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', 'in:women,men,mixed'],
            'handedness' => ['nullable', 'in:right,left'],
            'age_group' => ['required', 'in:'.implode(',', self::AGE_GROUPS)],
            'birth_year' => ['nullable', 'integer', 'between:1930,2026'],
            'usa_fencing_id' => ['nullable', 'string', 'max:30'],
            'home_zip' => ['nullable', 'string', 'max:10'],
            'home_club_id' => ['nullable', 'exists:clubs,id'],
            'goal' => ['nullable', 'in:'.implode(',', array_keys(config('fencing.goals')))],
            'drive_radius_miles' => ['required', 'integer', 'between:50,4000'],
            'primary_weapon' => ['nullable', 'in:'.implode(',', self::WEAPONS)],
        ]);

        if (empty($this->competedWeapons($request))) {
            throw ValidationException::withMessages(['weapons' => 'Pick at least one weapon.']);
        }

        return $data;
    }

    /** @return array{0: array<string,string>, 1: string} [weapon=>rating], primaryWeapon */
    private function weaponSelection(Request $request): array
    {
        $weapons = $this->competedWeapons($request);
        $primary = $request->input('primary_weapon');
        if (! isset($weapons[$primary])) {
            $primary = array_key_first($weapons);
        }

        return [$weapons, $primary];
    }

    /** @return array<string,string> weapon => rating, for the weapons the fencer competes */
    private function competedWeapons(Request $request): array
    {
        $out = [];
        foreach (self::WEAPONS as $w) {
            if ($request->boolean("compete_{$w}")) {
                $rating = $request->input("rating_{$w}", 'U');
                $out[$w] = in_array($rating, self::RATINGS, true) ? $rating : 'U';
            }
        }

        return $out;
    }

    private function rebuildWeapons(Fencer $fencer, array $weapons, string $primary): void
    {
        $fencer->weapons()->delete();
        foreach ($weapons as $weapon => $rating) {
            $fencer->weapons()->create([
                'weapon' => $weapon,
                'rating' => $rating,
                'is_primary' => $weapon === $primary,
            ]);
        }
    }

    private function applyGeocode(Fencer $fencer, ZipGeocoder $geocoder): void
    {
        if ($geo = $geocoder->lookup($fencer->home_zip)) {
            $fencer->home_lat = $geo['lat'];
            $fencer->home_lng = $geo['lng'];
        }
    }

    private function formData(Fencer $fencer): array
    {
        return [
            'fencer' => $fencer,
            'clubs' => Club::orderBy('name')->get(),
            'weaponsList' => self::WEAPONS,
            'ageGroups' => self::AGE_GROUPS,
            'ratings' => self::RATINGS,
            'goals' => config('fencing.goals'),
            'currentWeapons' => $fencer->exists ? $fencer->weapons->keyBy('weapon') : collect(),
        ];
    }
}
