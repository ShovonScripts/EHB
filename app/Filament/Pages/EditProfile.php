<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;

/**
 * Admin → My profile.
 *
 * Exists to make one thing obvious: because sign-in is a single passcode
 * (see App\Filament\Pages\Auth\Login), this is the only place it can be
 * changed, and a reader landing here should not have to infer that from a
 * field labelled "Password". So the password fields are relabelled "passcode"
 * and the email field says plainly that it is not used to sign in.
 *
 * Everything else — the current-passcode check, the password strength rule,
 * the hashing on save — is Filament's own implementation, untouched.
 */
class EditProfile extends BaseEditProfile
{
    public function getTitle(): string
    {
        return 'My profile';
    }

    public function getHeading(): string
    {
        return 'My profile';
    }

    public function getSubheading(): ?string
    {
        return 'Your passcode is the only thing that unlocks this panel.';
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('New passcode')
            ->validationAttribute('passcode')
            ->helperText('Use something long. This is the only credential for the whole panel.');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirm new passcode')
            ->validationAttribute('passcode confirmation');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->label('Current passcode')
            ->validationAttribute('current passcode')
            ->helperText('Required before a new passcode is saved.');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->helperText('Kept on record only — the sign-in form asks for your passcode, not this address.');
    }
}
