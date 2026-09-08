<?php

/**
 * Deutsche Fehlermeldungen für die Regeln, die diese App wirklich benutzt
 * (Registrierung, Profil, Masseneingabe, Einstellungen, Import).
 *
 * Bewusst keine vollständige Übersetzung aller Laravel-Regeln: Was hier fehlt,
 * fällt automatisch auf die englische Fassung des Frameworks zurück
 * (APP_FALLBACK_LOCALE=en). Eine halb gepflegte Volldatei wäre schlechter als
 * eine kurze, die stimmt — auffallen würde eine veraltete Zeile erst dem
 * Nutzer, dem sie angezeigt wird.
 */
return [
    'accepted' => ':attribute muss angenommen werden.',
    'array' => ':attribute muss eine Liste sein.',
    'boolean' => ':attribute kann nur ja oder nein sein.',
    'confirmed' => 'Die Wiederholung von :attribute stimmt nicht überein.',
    'current_password' => 'Das Passwort stimmt nicht.',
    'date' => ':attribute ist kein gültiges Datum.',
    'email' => 'Bitte gib eine gültige E-Mail-Adresse an.',
    'exists' => ':attribute ist uns nicht bekannt.',
    'file' => ':attribute muss eine Datei sein.',
    'image' => ':attribute muss ein Bild sein.',
    'in' => 'Für :attribute ist dieser Wert nicht vorgesehen.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'json' => ':attribute muss gültiges JSON sein.',
    'lowercase' => ':attribute darf nur aus Kleinbuchstaben bestehen.',
    'max' => [
        'array' => ':attribute darf höchstens :max Einträge haben.',
        'file' => ':attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
    ],
    'mimes' => ':attribute muss eine Datei vom Typ :values sein.',
    'min' => [
        'array' => ':attribute muss mindestens :min Einträge haben.',
        'file' => ':attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'numeric' => ':attribute muss eine Zahl sein.',
    'required' => ':attribute fehlt noch.',
    'string' => ':attribute muss Text sein.',
    'unique' => ':attribute wird schon verwendet.',
    'uploaded' => ':attribute konnte nicht hochgeladen werden.',

    /*
    | Namen, wie sie in den Meldungen auftauchen sollen. Ohne das stünde dort
    | der Feldname aus dem Formular ("password_confirmation").
    */
    'attributes' => [
        'name' => 'Der Name',
        'email' => 'Die E-Mail-Adresse',
        'password' => 'Das Passwort',
        'password_confirmation' => 'Die Wiederholung',
        'current_password' => 'Das aktuelle Passwort',
        'eingabe' => 'Die Eingabe',
        'datei' => 'Die Datei',
        'aktion' => 'Die Aktion',
        'modus' => 'Der Modus',
        'theme' => 'Das Farbschema',
        'per_page' => 'Die Seitengröße',
        'music_volume' => 'Die Lautstärke',
    ],
];
