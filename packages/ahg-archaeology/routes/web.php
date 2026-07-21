<?php

/**
 * Routes are NOT loaded from here. `/archaeology` is a single top-level segment
 * and must beat the locked `/{slug}` catch-all, which requires registration in
 * the provider's register() via callAfterResolving('router') - loadRoutesFrom()
 * in boot() runs too late. See AhgArchaeologyServiceProvider.
 */
