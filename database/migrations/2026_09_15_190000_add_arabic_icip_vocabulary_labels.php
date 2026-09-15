<?php

/**
 * Arabic labels for the ICIP cultural-sensitivity vocabulary.
 *
 * The labels live in data/vocabularies/icip.ttl (skos:prefLabel @ar), which
 * is the canonical source: a future `ahg:vocabulary-import` re-primes the
 * cache from Fuseki with them. That import needs Fuseki admin credentials
 * no instance keeps in its .env, so without this migration an Arabic
 * interface would keep showing "Sacred / secret" and "Age-restricted".
 *
 * Inserts an Arabic vocabulary_label_cache row only where the concept is
 * already cached in English and has no Arabic row, copying that row's
 * endpoint so the cache stays consistent. Re-running is a no-op; down()
 * removes only rows that still carry exactly the label inserted here.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE = 'https://heratio.theahg.co.za/vocabulary/icip#';

    private function labels(): array
    {
        // [concept, Arabic prefLabel] - same values as icip.ttl
        return [
            ['SensitivityLevel', 'مستوى الحساسية'],
            ['RestrictionType', 'نوع القيد'],
            ['KnowledgeType', 'نوع المعرفة'],
            ['CustodianRole', 'دور الحافظ'],
            ['RightsType', 'نوع حقوق ICIP'],
            ['Open', 'مفتوح'],
            ['Restricted', 'مقيد'],
            ['CulturallySensitive', 'حساس ثقافياً'],
            ['SacredSecret', 'مقدس / سري'],
            ['GenderRestricted', 'مقيد حسب الجنس'],
            ['AgeRestricted', 'مقيد حسب العمر'],
            ['DeceasedPersonsContent', 'محتوى يخص أشخاصاً متوفين'],
            ['CommunityOnly', 'وصول مقصور على المجتمع'],
            ['CeremonialOnly', 'للاستخدام الطقسي فقط'],
            ['TemporaryWithdrawal', 'سحب مؤقت'],
            ['PermanentRestriction', 'قيد دائم'],
            ['OralTradition', 'تقليد شفوي'],
            ['TraditionalSong', 'أغنية تقليدية'],
            ['TraditionalDance', 'رقصة تقليدية'],
            ['TraditionalDesign', 'تصميم / زخرفة تقليدية'],
            ['TraditionalKnowledge', 'معارف تقليدية'],
            ['LanguageMaterial', 'مادة لغوية'],
            ['PlaceName', 'اسم مكان'],
            ['Genealogy', 'علم الأنساب / القرابة'],
            ['CulturalObject', 'قطعة ثقافية'],
            ['Elder', 'شيخ المجتمع'],
            ['KnowledgeHolder', 'حامل المعرفة'],
            ['CulturalAuthority', 'هيئة السلطة الثقافية'],
            ['Family', 'عائلة / عشيرة'],
            ['Acknowledgment', 'حق الاعتراف'],
            ['Attribution', 'حق النسب'],
            ['Consent', 'الموافقة الحرة والمسبقة والمستنيرة (FPIC)'],
            ['Integrity', 'حق السلامة'],
            ['NoCommercialUse', 'لا استخدام تجاري دون موافقة'],
            ['Repatriation', 'حق الإعادة إلى الوطن'],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('vocabulary_label_cache')) {
            return;
        }

        foreach ($this->labels() as [$concept, $ar]) {
            $uri = self::BASE.$concept;
            $en = DB::table('vocabulary_label_cache')->where(['uri' => $uri, 'culture' => 'en'])->first();
            $hasArabic = DB::table('vocabulary_label_cache')->where(['uri' => $uri, 'culture' => 'ar'])->exists();
            if ($en && ! $hasArabic) {
                DB::table('vocabulary_label_cache')->insert([
                    'uri' => $uri,
                    'culture' => 'ar',
                    'preferred_label' => $ar,
                    'alt_labels' => '[]',
                    'source_vocabulary' => 'icip',
                    'sparql_endpoint' => $en->sparql_endpoint,
                    'expires_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('vocabulary_label_cache')) {
            return;
        }

        foreach ($this->labels() as [$concept, $ar]) {
            DB::table('vocabulary_label_cache')->where(['uri' => self::BASE.$concept, 'culture' => 'ar', 'preferred_label' => $ar])->delete();
        }
    }
};
