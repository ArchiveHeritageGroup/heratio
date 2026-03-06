<?php

namespace AhgCore\Traits;

trait HasI18n
{
    /**
     * Get translated attribute value for a given culture.
     */
    public function getTranslated(string $attribute, string $culture = 'en')
    {
        $i18n = $this->i18n()->where('culture', $culture)->first();

        return $i18n?->{$attribute};
    }

    /**
     * Get all translations for a culture.
     */
    public function getI18nForCulture(string $culture = 'en')
    {
        return $this->i18n()->where('culture', $culture)->first();
    }
}
