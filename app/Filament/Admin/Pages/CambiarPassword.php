<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Closure;

class CambiarPassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static string $view = 'filament.admin.pages.cambiar-password';
    protected static ?string $slug = 'cambiar-password';
    protected static ?string $title = 'Cambiar Contraseña';
    protected static ?string $navigationLabel = 'Cambiar Contraseña';
    protected static ?int $navigationSort = 100;

    // Ocultar del menú lateral (se accede desde el menú de usuario)
    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public function mount(): void
    {
        //
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('current_password')
                    ->password()
                    ->required()
                    ->revealable(true)
                    ->label('Contraseña Actual')
                    ->rules([
                        function () {
                            return function (string $attribute, $value, Closure $fail) {
                                if (! Hash::check($value, auth()->user()->password)) {
                                    $fail('La contraseña actual es incorrecta.');
                                }
                            };
                        },
                    ]),

                Forms\Components\TextInput::make('new_password')
                    ->password()
                    ->required()
                    ->same('password_confirmation')
                    ->minLength(8)
                    ->label('Nueva Contraseña')
                    ->revealable(true)
                    ->helperText('Mínimo 8 caracteres'),

                Forms\Components\TextInput::make('password_confirmation')
                    ->password()
                    ->required()
                    ->label('Confirmar Nueva Contraseña')
                    ->revealable(true),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->form->validate();

        $data = $this->data;

        // Check if the current password is correct
        if (!Hash::check($data['current_password'], Auth::user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña proporcionada no coincide con tu contraseña actual.',
            ]);
        }

        // Update the password if validation passes
        Auth::user()->update([
            'password' => $data['new_password'],
        ]);

        // Refill the form with the reset data
        $this->form->fill();

        session()->put([
            'password_hash_' . Auth::getDefaultDriver() => Auth::user()->password
        ]);

        // Success notification
        Notification::make()
            ->title('¡Contraseña actualizada exitosamente!')
            ->success()
            ->send();
    }
}
