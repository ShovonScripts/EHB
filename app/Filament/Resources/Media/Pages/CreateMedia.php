<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Add one file to the media library, by hand.
 *
 * The upload fields on the content and profile forms create their Media rows
 * themselves, so this page is the way in for a file that has no owner yet — a
 * portrait, a logo, a share card — and the only way to correct one afterwards.
 */
class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    /**
     * Stamp the uploader onto the row.
     *
     * `media.uploaded_by` is a NOT NULL foreign key, and the form's field for
     * it is `disabled()` — read-only, showing who is uploading. In Filament 5 a
     * disabled field is excluded from dehydration (`disabled()` implies
     * `saved(false)`; Filament's own StateTest is explicit that "disabled
     * components are excluded from state dehydration"), so nothing arrived in
     * the insert data for a required column and *creating a media row failed on
     * the foreign key*. The screen existed but could only ever error.
     *
     * Written here rather than by dehydrating the field: the uploader is audit
     * metadata, and a dehydrated field is client-controllable — the request
     * could name any user as the uploader. This hook runs after dehydration, so
     * the authenticated user wins over anything the request carried.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = Auth::id();

        return $data;
    }
}
