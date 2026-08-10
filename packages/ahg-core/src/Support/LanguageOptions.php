<?php

/**
 * LanguageOptions - canonical ISO-639 language / ISO-15924 script pick-lists.
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

namespace AhgCore\Support;

/**
 * Single source of truth for the archival "language(s) of description /
 * language(s) of material" and "script(s)" select pick-lists.
 *
 * These 28-language and 14-script maps were previously copy-pasted, byte
 * for byte, into three places (the information-object edit blade, the
 * InformationObjectController display maps, and the repository edit blade).
 * They all now delegate here so the list is maintained once. Array order
 * is preserved because it is the dropdown display order.
 *
 * NOTE: other language lists in the codebase are deliberately different
 * (the actor edit form's full ~136-entry ISO-639 set, the UI-locale
 * switcher, OPAC search facets, translation-target lists) and are NOT
 * consolidated here - they serve different purposes.
 */
class LanguageOptions
{
    /**
     * ISO-639 code => English display name, in dropdown order.
     * Description / material language pick-list.
     *
     * @return array<string,string>
     */
    public static function descriptionLanguages(): array
    {
        return [
            'en' => 'English', 'af' => 'Afrikaans', 'nl' => 'Dutch', 'de' => 'German',
            'fr' => 'French', 'zu' => 'Zulu', 'xh' => 'Xhosa', 'st' => 'Sesotho',
            'tn' => 'Setswana', 'nso' => 'Sepedi', 'ts' => 'Tsonga', 'ss' => 'Swati',
            've' => 'Venda', 'nr' => 'Ndebele', 'pt' => 'Portuguese', 'es' => 'Spanish',
            'it' => 'Italian', 'la' => 'Latin', 'grc' => 'Ancient Greek', 'he' => 'Hebrew',
            'ar' => 'Arabic', 'fa' => 'Persian', 'hi' => 'Hindi', 'zh' => 'Chinese',
            'ja' => 'Japanese', 'ko' => 'Korean', 'ru' => 'Russian', 'sw' => 'Swahili',
        ];
    }

    /**
     * ISO-15924 script code => English display name, in dropdown order.
     *
     * @return array<string,string>
     */
    public static function scripts(): array
    {
        return [
            'Latn' => 'Latin', 'Cyrl' => 'Cyrillic', 'Arab' => 'Arabic', 'Grek' => 'Greek',
            'Hebr' => 'Hebrew', 'Deva' => 'Devanagari', 'Hans' => 'Chinese (Simplified)',
            'Hant' => 'Chinese (Traditional)', 'Jpan' => 'Japanese', 'Kore' => 'Korean',
            'Thai' => 'Thai', 'Geor' => 'Georgian', 'Armn' => 'Armenian', 'Ethi' => 'Ethiopic',
        ];
    }
}
