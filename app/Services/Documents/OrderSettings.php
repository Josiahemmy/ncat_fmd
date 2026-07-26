<?php

namespace App\Services\Documents;

use App\Models\AppSetting;

/**
 * The department-editable blocks printed on the order forms: the letterhead
 * address, the two NCAT contacts, and the prepared-by line.
 *
 * Defaults are transcribed from the sample forms. Note that the two forms do
 * NOT agree: the Purchase Order signs off "Head, Materials and Stores." and the
 * Repair Order signs off "Materials and Stores." with no "Head,". Both are
 * reproduced as printed rather than normalised, and each is separately
 * editable, so the department can settle it without a deploy.
 */
class OrderSettings
{
    public const KEY = 'order_document';

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'address_line_1' => 'ZARIA AERODROME PMB 1031',
            'address_line_2' => 'Kaduna state, Nigeria',
            // Transcribed verbatim from both samples. It reads as a typo on the
            // paper (two addresses run together); flagged for the department
            // rather than silently corrected.
            'email_line_1' => 'rector@ncat.ng.info@ncat.gov.ng',
            'email_line_2' => 'hfmd@ncat.gov.ng',
            'website' => 'www.ncat.gov.ng',
            'contact_1_name' => 'IBRAHIM M. HIRSE',
            'contact_1_email' => 'hquality@ncat.gov.ng',
            'contact_2_name' => 'GAMMANIEL M. DANBATURE',
            'contact_2_email' => 'hfmd@ncat.gov.ng',
            'po_prepared_by' => 'Head, Materials and Stores.',
            'ro_prepared_by' => 'Materials and Stores.',
            'po_note' => 'No invoice or debit note covering supplies will be accepted for payment '
                .'unless such supplies are covered by a purchase order signed by person authorized '
                .'to do so by this College. Suppliers are advised to obtained signatures of person '
                .'authorized to sign purchase order on behalf of this College prior to accepting any '
                .'such orders.',
            'ro_note' => 'This item is for Repair and Test. Please send Repair Quotation (Total Cost '
                .'and Lead Time) Repair stations/organizations are advised to obtain Signature of '
                .'person authorized to sign Repair order on behalf of this College prior to accepting '
                .'any such orders.',
        ];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = AppSetting::find(self::KEY)?->value ?? [];

        // Defaults fill any key the stored record predates, so adding a field
        // later does not blank it out on documents saved before this release.
        return array_merge($this->defaults(), array_filter(
            $stored,
            fn ($v) => $v !== null && $v !== '',
        ));
    }

    public function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    /** @param  array<string, mixed>  $values */
    public function save(array $values): void
    {
        AppSetting::updateOrCreate(
            ['key' => self::KEY],
            ['value' => array_intersect_key($values, $this->defaults())],
        );
    }
}
