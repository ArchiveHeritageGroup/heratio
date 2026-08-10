<?php

/*
 * Artwork placement requests configuration (#1459).
 */

return [
    // Do not chase the same overdue placement more often than this (days).
    'reminder_every_days' => 7,

    // How far ahead a courtesy "due back soon" reminder looks (days).
    'due_soon_within_days' => 7,
];
