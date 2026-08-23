<?php

/**
 * SeedDemoPiiCommand - synthetic, clearly-labelled PII for the POPIA demo.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgPrivacy\Console\Commands;

use AhgCore\Support\StatusRow;
use AhgPrivacy\Services\PiiScanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Demonstration records carrying personal information, for showing the POPIA
 * surfaces - PII scan, confidence gates, review and redaction - without ever
 * putting a real person's data on a screen.
 *
 * EVERY IDENTITY NUMBER HERE IS IMPOSSIBLE. A South African ID number's
 * eleventh digit is the citizenship digit and Home Affairs defines exactly two
 * values: 0 (citizen) and 1 (permanent resident). Every number generated here
 * uses 2. It cannot belong to any living person, and it cannot be made to by
 * accident.
 *
 * It is still DETECTED, because PiiScanService::validateSaIdNumber() checks the
 * 13-digit shape, an all-zeros sentinel and the Luhn checksum - not the
 * citizenship digit. So the demo shows a real high-confidence detection over
 * data that is structurally incapable of identifying anybody.
 *
 * The confidence split is deliberate and is the point of the demo. The scanner
 * scores a 13-digit match at 0.70, adds 0.20 when the checksum validates and
 * subtracts 0.40 when it fails (floored at 0.30). Seeding both kinds puts two
 * ID-shaped strings in one document at 0.90 and 0.30 - which is a confidence
 * gate you can point at, rather than assert.
 *
 * Labelling is not decoration. Every record is titled [SYNTHETIC], carries a
 * banner in its scope note, and is filed under one clearly-named parent, so a
 * screenshot that escapes the room still says what it is.
 */
class SeedDemoPiiCommand extends Command
{
    protected $signature = 'ahg:privacy-seed-demo-pii
        {--repository= : repository.id to file the records under}
        {--scan : run the PII scan over each record after seeding}
        {--force : re-seed even if the collection already exists}';

    protected $description = 'Seed clearly-labelled synthetic PII records for the POPIA demonstration';

    private const BANNER = '*** SYNTHETIC DEMONSTRATION RECORD - NOT A REAL PERSON ***'
        . ' Every name, identity number, address, contact detail and health statement below is'
        . ' fabricated for training and demonstration. The identity numbers use citizenship digit 2,'
        . ' which South African identity numbers never use, so none can belong to a living person.';

    private const PARENT_TITLE = '[SYNTHETIC] POPIA demonstration set - fabricated personal information';

    public function handle(): int
    {
        foreach (['object', 'information_object', 'information_object_i18n', 'slug'] as $t) {
            if (! DB::getSchemaBuilder()->hasTable($t)) {
                $this->error("Table {$t} is missing - is this a Heratio install?");

                return self::FAILURE;
            }
        }

        $existing = DB::table('information_object_i18n')->where('title', self::PARENT_TITLE)->value('id');
        if ($existing && ! $this->option('force')) {
            $this->warn("Already seeded as information_object {$existing}. Use --force to add another set.");

            return self::SUCCESS;
        }

        $repositoryId = $this->option('repository') ? (int) $this->option('repository') : null;

        $parentId = $this->createRecord(
            self::PARENT_TITLE,
            self::BANNER . ' This collection exists so the privacy and redaction screens can be'
                . ' demonstrated on data that is safe to project in a public room.',
            null,
            $repositoryId
        );
        $this->info("Parent collection: information_object {$parentId}");

        $seeded = [];
        foreach ($this->records() as $rec) {
            $id = $this->createRecord($rec['title'], $rec['body'], $parentId, $repositoryId);
            $seeded[$id] = $rec['title'];
            $this->line("  {$id}  {$rec['title']}");
        }

        if ($this->option('scan')) {
            $this->newLine();
            $this->info('Scanning...');
            $svc = new PiiScanService('popia');
            foreach ($seeded as $id => $title) {
                $body = DB::table('information_object_i18n')->where('id', $id)
                    ->where('culture', 'en')->value('scope_and_content');
                $findings = $svc->scan((string) $body);
                $svc->scanAndPersist((string) $body, $id, null);

                $byType = [];
                foreach ($findings as $f) {
                    $byType[$f['type']][] = $f['confidence'];
                }
                $parts = [];
                foreach ($byType as $type => $confs) {
                    $parts[] = $type . ' x' . count($confs) . ' (' . implode(', ', array_map(
                        static fn ($c) => number_format($c, 2), array_unique($confs))) . ')';
                }
                $this->line('  ' . $id . '  ' . implode('; ', $parts ?: ['no findings']));
            }
        }

        $this->newLine();
        $this->info('Done. Every identity number uses citizenship digit 2 and cannot be a real person.');

        return self::SUCCESS;
    }

    /**
     * A South African ID: YYMMDD SSSS C A Z.
     * C is forced to 2 - a value Home Affairs never issues - so the number is
     * structurally incapable of matching a living person. $validChecksum
     * decides whether the Luhn digit is correct, which is what moves the
     * scanner between 0.90 and 0.30 confidence.
     */
    private function saId(string $yymmdd, int $sequence, bool $validChecksum): string
    {
        $twelve = $yymmdd . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT) . '2' . '8';
        $check = $this->luhnCheckDigit($twelve);
        if (! $validChecksum) {
            $check = ($check + 5) % 10;   // deliberately wrong, still a digit
        }

        return $twelve . $check;
    }

    private function luhnCheckDigit(string $twelve): int
    {
        $sum = 0;
        $digits = str_split(strrev($twelve));
        foreach ($digits as $i => $d) {
            $n = (int) $d;
            if ($i % 2 === 0) {           // will be the odd positions once the check digit is appended
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /** @return array<int,array{title:string,body:string}> */
    private function records(): array
    {
        $b = self::BANNER . "\n\n";

        return [
            [
                'title' => '[SYNTHETIC] Provincial hospital admission register - extract, 1987',
                'body' => $b . "Admission register, Bloemfontein Provincial Hospital, entry 447 of 1987.\n\n"
                    . 'Patient: Nomvula Thandiwe Mahlangu. Identity number ' . $this->saId('870314', 5127, true) . ".\n"
                    . "Residential address: 14 Buitenkant Street, Heidedal, Bloemfontein, 9301.\n"
                    . "Contact: 051 447 8823. Next of kin: Sipho Mahlangu, 082 551 4470.\n\n"
                    . "Clinical note: admitted with pulmonary tuberculosis, sputum smear positive. "
                    . "Treatment commenced 16 March 1987. Patient also recorded as hypertensive and "
                    . "insulin-dependent diabetic. HIV status not recorded at admission.\n\n"
                    . 'A second identity number appears in the margin in a different hand: '
                    . $this->saId('870314', 5127, false) . ' - transcription uncertain.',
            ],
            [
                'title' => '[SYNTHETIC] Municipal indigent support application - 2004',
                'body' => $b . "Application for indigent household registration, Mangaung Local Municipality.\n\n"
                    . 'Applicant: Johannes Petrus van der Merwe. Identity number ' . $this->saId('561122', 834, true) . ".\n"
                    . "Address: 27 Kerkstraat, Bainsvlei, Bloemfontein, 9338. Telephone 051 522 9014.\n"
                    . "Email: jpvdm1956@webmail.example.\n\n"
                    . "Declared household income: R1 840 per month. Applicant states he is a pensioner "
                    . "and receives a disability grant following a stroke in 2001, with ongoing "
                    . "left-side paralysis. Spouse: Anna Sophia van der Merwe, identity number "
                    . $this->saId('590803', 471, true) . ".\n\n"
                    . 'Reference on reverse, partially legible: ' . $this->saId('561122', 834, false) . '.',
            ],
            [
                'title' => '[SYNTHETIC] Departmental personnel file memorandum - 1994',
                'body' => $b . "Internal memorandum, Department of Education, Northern Transvaal.\n\n"
                    . 'Subject: Transfer application, Mr Kagiso Reuben Molefe, identity number '
                    . $this->saId('681007', 6612, true) . ".\n"
                    . "Present station: Mokopane Primary. Home address: House 1142, Zone 4, Seshego, 0742.\n"
                    . "Telephone (h) 015 223 7761, (w) 015 291 4400.\n\n"
                    . "The applicant has disclosed a chronic back injury sustained in 1991 and requests "
                    . "a post that does not require supervision of physical education. Medical "
                    . "certificate from Dr M. Ncube attached, dated 3 February 1994.\n\n"
                    // The canonical test PAN. Luhn-valid, so the scanner actually
                    // fires (it discards non-Luhn candidates as too noisy), and
                    // universally reserved for testing so it can never be a real card.
                    . "Card number quoted on the attached claim form: 4111 1111 1111 1111.",
            ],
            [
                'title' => '[SYNTHETIC] Correspondence with medical scheme - 1999',
                'body' => $b . "Letter to the Registrar of Medical Schemes.\n\n"
                    . 'Member: Fatima Bibi Patel, identity number ' . $this->saId('720519', 3308, true) . ".\n"
                    . "Postal address: PO Box 1187, Laudium, 0037. Cell 083 447 2219.\n"
                    . "Date of birth 1972-05-19.\n\n"
                    . "The member disputes the rejection of a claim for oncology treatment following a "
                    . "diagnosis of stage II breast carcinoma in August 1999. The scheme's decision "
                    . "cites a pre-existing condition exclusion. Correspondence attached.\n\n"
                    . 'A dependent is listed: Yusuf Patel, identity number ' . $this->saId('980226', 5540, false) . '.',
            ],
        ];
    }

    /**
     * Create an information_object with its CTI object row, i18n, slug and a
     * DRAFT publication status. Draft is deliberate: demonstration records
     * carrying personal information should not be publicly reachable, and the
     * demo is given by an authenticated operator.
     */
    private function createRecord(string $title, string $body, ?int $parentId, ?int $repositoryId): int
    {
        return DB::transaction(function () use ($title, $body, $parentId, $repositoryId) {
            $objectId = (int) DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('information_object')->insert([
                'id' => $objectId,
                'parent_id' => $parentId,
                'repository_id' => $repositoryId,
                'lft' => 0,
                'rgt' => 0,
                'source_culture' => 'en',
            ]);

            DB::table('information_object_i18n')->insert([
                'id' => $objectId,
                'culture' => 'en',
                'title' => $title,
                'scope_and_content' => $body,
            ]);

            DB::table('slug')->insert([
                'object_id' => $objectId,
                'slug' => Str::slug(Str::limit($title, 90, '')) . '-' . $objectId,
            ]);

            // 158 = publication status, 159 = draft.
            StatusRow::set($objectId, 158, 159);

            return $objectId;
        });
    }
}
