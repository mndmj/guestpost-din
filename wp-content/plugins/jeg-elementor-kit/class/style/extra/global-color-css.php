<?php
defined( 'ABSPATH' ) || exit();
// INCLUDED IN CLASS CSS.
$css .= '
body {
    --jkit-primary-color: var(--e-global-color-jkit_primary, ' . $json_settings['JColorPrimary'] . ' ) !important;
    --jkit-secondary-color: var(--e-global-color-jkit_secondary, ' . $json_settings['JColorSecondary'] . ' ) !important;
    --jkit-text-color: var(--e-global-color-jkit_text, ' . $json_settings['JColorText'] . ' ) !important;
    --jkit-accent-color: var(--e-global-color-jkit_accent, ' . $json_settings['JColorAccent'] . ' ) !important;
    --jkit-tertiary-color: var(--e-global-color-jkit_tertiary, ' . $json_settings['JColorTertiary'] . ' ) !important;
    --jkit-meta-color: var(--e-global-color-jkit_meta, ' . $json_settings['JColorMeta'] . ' ) !important;
    --jkit-border-color: var(--e-global-color-jkit_border, ' . $json_settings['JColorBorder'] . ' ) !important;
}
';
