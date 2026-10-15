<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\Toasts;
use App\Services\ProjectHealthService;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Settings')]
class SettingsPage extends Component
{
    use Toasts;

    /** @var array<string, mixed> key => value; dots in keys are replaced with "__" for wire:model */
    public array $values = [];

    public function mount(): void
    {
        $this->authorize('admin');
        foreach (Settings::all() as $key => $value) {
            $this->values[$this->field($key)] = $value;
        }
    }

    private function field(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    public function save(ProjectHealthService $health): void
    {
        $this->authorize('admin');

        $rules = [];
        foreach (Settings::DEFINITIONS as $key => $definition) {
            $rules['values.'.$this->field($key)] = match ($definition['type']) {
                'int' => ['required', 'integer', 'min:'.($definition['min'] ?? 0), 'max:'.($definition['max'] ?? 100000)],
                'bool' => ['boolean'],
                default => ['required', 'string', 'max:100'],
            };
        }
        $attributes = collect(Settings::DEFINITIONS)->mapWithKeys(fn ($d, $key) => ['values.'.$this->field($key) => $d['label']])->all();
        $this->validate($rules, [], $attributes);

        $changes = [];
        $current = Settings::all();
        foreach (Settings::DEFINITIONS as $key => $definition) {
            $value = $this->values[$this->field($key)];
            $value = match ($definition['type']) {
                'int' => (int) $value,
                'bool' => (bool) $value,
                default => trim((string) $value),
            };
            if ($value !== $current[$key]) {
                $changes[$key] = $value;
            }
        }

        if (! $changes) {
            $this->toast('Nothing changed.', 'info');

            return;
        }

        Settings::set($changes);
        Audit::log('settings.updated', 'Changed settings: '.implode(', ', array_keys($changes)), null, ['changes' => $changes]);

        // New thresholds take effect on every project right away.
        if (collect($changes)->keys()->contains(fn ($k) => str_starts_with($k, 'health.') || $k === 'deadline.due_soon_days')) {
            $health->refreshAll();
        }

        $this->toast('Settings saved.');
    }

    public function render(): View
    {
        return view('livewire.admin.settings-page', ['groups' => Settings::grouped()]);
    }
}
