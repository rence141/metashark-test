<?php
/**
 * Simple translation system
 * Loads language JSON files and provides translate() helper
 */

$currentLanguage = $_SESSION['language'] ?? 'en';
$translations = [];

function loadLanguage($lang = 'en') {
    global $translations, $currentLanguage;
    
    $langFile = __DIR__ . "/../languages/{$lang}.json";
    
    if (file_exists($langFile)) {
        $translations = json_decode(file_get_contents($langFile), true) ?? [];
    } else {
        // Fallback to English
        $langFile = __DIR__ . "/../languages/en.json";
        if (file_exists($langFile)) {
            $translations = json_decode(file_get_contents($langFile), true) ?? [];
        }
    }
    
    $currentLanguage = $lang;
}

function t($key, $default = '') {
    global $translations;
    
    // Support nested keys like "seller.profile.title"
    $keys = explode('.', $key);
    $value = $translations;
    
    foreach ($keys as $k) {
        if (is_array($value) && isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default ?: $key;
        }
    }
    
    return $value;
}

// Load the current language
loadLanguage($currentLanguage);
?>
