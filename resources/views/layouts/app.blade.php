{{--
 | layouts.app - compatibility wrapper
 |
 | Some package views (ahg-settings sharepoint-settings, ahg-jobs browse/show)
 | were authored against a generic `layouts.app` that was never registered in
 | this app, causing "View [layouts.app] not found" 500s (#1291). This thin
 | wrapper maps the conventional `layouts.app` onto the active Bootstrap 5
 | theme's single-column layout. Child sections (`title`, `content`,
 | `body-class`, `before-content`, `after-content`) propagate unchanged.
 |
 | @author    Johan Pieterse
 | @copyright Plain Sailing Information Systems
 | @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
