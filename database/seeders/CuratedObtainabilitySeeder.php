<?php

namespace Database\Seeders;

use App\Enums\Difficulty;
use App\Enums\ObtainMethod;
use App\Models\Game;
use App\Models\Obtainability;
use App\Models\Pokemon;
use Illuminate\Database\Seeder;

/**
 * Bezugsquellen, die die PokéAPI nicht liefert (spec.md 2.3, 4).
 *
 * `location-area-encounters` deckt nur Wildfänge ab. Starter, Fossilien und
 * Event-Verteilungen müssen deshalb kuratiert dazu – ohne sie hielte die
 * Mehrfach-Fang-Empfehlung (2.8) z.B. Bisaflor für gar nicht erreichbar,
 * weil Bisasam nirgends „wild" vorkommt.
 *
 * Ergänzungen gehören hier hinein oder per `pokedex:import-sources` aus einer
 * CSV – der Seeder ist idempotent.
 */
class CuratedObtainabilitySeeder extends Seeder
{
    /** pokeapi-slug => [game-slugs] – Starter als Geschenk zu Spielbeginn. */
    public const STARTERS = [
        'bulbasaur' => ['red', 'blue', 'yellow', 'firered', 'leafgreen', 'lets-go-pikachu', 'lets-go-eevee'],
        'charmander' => ['red', 'blue', 'yellow', 'firered', 'leafgreen', 'lets-go-pikachu', 'lets-go-eevee'],
        'squirtle' => ['red', 'blue', 'yellow', 'firered', 'leafgreen', 'lets-go-pikachu', 'lets-go-eevee'],
        'pikachu' => ['yellow', 'lets-go-pikachu'],
        'eevee' => ['lets-go-eevee'],

        'chikorita' => ['gold', 'silver', 'crystal', 'heartgold', 'soulsilver'],
        'cyndaquil' => ['gold', 'silver', 'crystal', 'heartgold', 'soulsilver', 'legends-arceus'],
        'totodile' => ['gold', 'silver', 'crystal', 'heartgold', 'soulsilver'],

        'treecko' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'torchic' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'mudkip' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],

        'turtwig' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'chimchar' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'piplup' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],

        'snivy' => ['black', 'white', 'black-2', 'white-2'],
        'tepig' => ['black', 'white', 'black-2', 'white-2'],
        'oshawott' => ['black', 'white', 'black-2', 'white-2', 'legends-arceus'],

        'chespin' => ['x', 'y'],
        'fennekin' => ['x', 'y'],
        'froakie' => ['x', 'y'],

        'rowlet' => ['sun', 'moon', 'ultra-sun', 'ultra-moon', 'legends-arceus'],
        'litten' => ['sun', 'moon', 'ultra-sun', 'ultra-moon'],
        'popplio' => ['sun', 'moon', 'ultra-sun', 'ultra-moon'],

        'grookey' => ['sword', 'shield'],
        'scorbunny' => ['sword', 'shield'],
        'sobble' => ['sword', 'shield'],

        'sprigatito' => ['scarlet', 'violet'],
        'fuecoco' => ['scarlet', 'violet'],
        'quaxly' => ['scarlet', 'violet'],
    ];

    /** pokeapi-slug => [game-slugs] – Fossil-Wiederbelebung. */
    public const FOSSILS = [
        'omanyte' => ['red', 'blue', 'yellow', 'firered', 'leafgreen'],
        'kabuto' => ['red', 'blue', 'yellow', 'firered', 'leafgreen'],
        'aerodactyl' => ['red', 'blue', 'yellow', 'firered', 'leafgreen'],
        'lileep' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'anorith' => ['ruby', 'sapphire', 'emerald', 'omega-ruby', 'alpha-sapphire'],
        'cranidos' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'shieldon' => ['diamond', 'pearl', 'platinum', 'brilliant-diamond', 'shining-pearl'],
        'tirtouga' => ['black', 'white', 'black-2', 'white-2'],
        'archen' => ['black', 'white', 'black-2', 'white-2'],
        'tyrunt' => ['x', 'y'],
        'amaura' => ['x', 'y'],
    ];

    /**
     * Mysteriöse Pokémon: wurden ausschließlich über abgelaufene Verteilungen
     * ausgegeben. Sie landen dadurch auf ⚪ „nur noch per Tausch/Community"
     * (spec.md 2.7). Ausnahmen mit regulärem Fangweg stehen nicht in der Liste.
     */
    public const EXPIRED_EVENT_MYTHICALS = [
        'mew', 'celebi', 'jirachi', 'deoxys', 'phione', 'manaphy', 'darkrai',
        'shaymin', 'arceus', 'victini', 'keldeo', 'meloetta', 'genesect',
        'diancie', 'hoopa', 'volcanion', 'magearna', 'marshadow', 'zeraora',
        'zarude',
    ];

    /**
     * Geschenke außerhalb des Spielanfangs, die die PokeAPI nicht kennt.
     *
     * pokeapi-slug => [game-slugs => Beschreibung]
     */
    public const GIFTS = [
        // Kronen-Schneelande (DLC): Cosmog steht im Haus in Freezington und
        // wird uebergeben, nachdem der Angriff auf das Dorf beendet ist.
        // Ohne diesen Eintrag haengt die ganze Linie -- Cosmovum, Solgaleo und
        // Lunala erben ihren Weg von Cosmog -- allein an Sonne/Mond und
        // Ultrasonne/Ultramond und damit an der Bank-Frist, obwohl sie in einem
        // Switch-Titel direkt an HOME zu holen ist.
        'cosmog' => ['sword', 'shield'],
    ];

    /**
     * Omega Rubin / Alpha Saphir verschenken nach der Story weitere Starter.
     *
     * Prof. Birk gibt sie in drei Schueben, jeweils an einen Fortschritt
     * gebunden. Die PokeAPI kennt nur den Encounter dahinter und fuehrt alle
     * neun als Wildfang auf Route 101 -- dort laufen aber nur Zigzachs,
     * Waumpel und Fiffyen herum.
     *
     * Quelle: Bulbapedia, "Professor Birch".
     */
    public const ORAS_GIFT_JOHTO = [
        'chikorita' => ['omega-ruby', 'alpha-sapphire'],
        'cyndaquil' => ['omega-ruby', 'alpha-sapphire'],
        'totodile' => ['omega-ruby', 'alpha-sapphire'],
    ];

    public const ORAS_GIFT_UNOVA = [
        'snivy' => ['omega-ruby', 'alpha-sapphire'],
        'tepig' => ['omega-ruby', 'alpha-sapphire'],
        'oshawott' => ['omega-ruby', 'alpha-sapphire'],
    ];

    public const ORAS_GIFT_SINNOH = [
        'turtwig' => ['omega-ruby', 'alpha-sapphire'],
        'chimchar' => ['omega-ruby', 'alpha-sapphire'],
        'piplup' => ['omega-ruby', 'alpha-sapphire'],
    ];

    /**
     * Arten, die es nur in Hisui gibt (Legenden: Arceus).
     *
     * Sie tragen Gen-8-Dexnummern, kommen in Schwert/Schild aber nicht vor.
     * `pokedex:fill-gaps` haengt Arten ohne jeden Fundweg ans Hauptspiel ihrer
     * Generation -- und das ist fuer Generation 8 Schwert/Schild. Salmagnis und
     * Cupidos standen dadurch als Wildfang in Schwert, wo sie niemand findet.
     *
     * Mit einem echten Eintrag hier sind sie keine Luecke mehr, und der
     * Fallback fasst sie gar nicht erst an.
     */
    public const HISUI_EXCLUSIVE = [
        'wyrdeer' => ['legends-arceus'],
        'kleavor' => ['legends-arceus'],
        'ursaluna' => ['legends-arceus'],
        'basculegion' => ['legends-arceus'],
        'sneasler' => ['legends-arceus'],
        'overqwil' => ['legends-arceus'],
        'enamorus' => ['legends-arceus'],
    ];

    /**
     * Legendaere aus den Dyna-Raids im Max-Lager (Kronen-Schneelande, DLC).
     *
     * Der Eintrag haengt bewusst an BEIDEN Editionen, obwohl ein Teil je nach
     * Version nur bei der einen erscheint: Die Versionsbindung gilt nur, wenn
     * man selbst hostet. Wer bei jemand anderem mitgeht, trifft auch die Arten
     * der anderen Edition -- fuer die Frage "komme ich da noch dran?" sind sie
     * also in beiden erreichbar.
     *
     * Quelle der Artenliste: Uebersicht der Dyna-Raid-Legendaeren (Game8),
     * abgeglichen mit Bulbapedia zum Ablauf.
     */
    public const DYNAMAX_ADVENTURES = [
        'articuno', 'zapdos', 'moltres', 'mewtwo',
        'raikou', 'entei', 'suicune', 'lugia', 'ho-oh',
        'latias', 'latios', 'kyogre', 'groudon', 'rayquaza',
        'uxie', 'mesprit', 'azelf', 'dialga', 'palkia', 'heatran', 'giratina', 'cresselia',
        'tornadus', 'thundurus', 'landorus', 'reshiram', 'zekrom', 'kyurem',
        'xerneas', 'yveltal', 'zygarde',
        'tapu-koko', 'tapu-lele', 'tapu-bulu', 'tapu-fini', 'solgaleo', 'lunala', 'necrozma',
        'nihilego', 'buzzwole', 'pheromosa', 'xurkitree', 'celesteela', 'kartana',
        'guzzlord', 'stakataka', 'blacephalon',
    ];

    /**
     * Spiele, in denen ein Starter tatsaechlich auch wild vorkommt.
     *
     * In Let's Go laufen Bisasam, Glumanda und Schiggy wirklich in der Welt
     * herum (Vertania-Wald, Felstunnel, Zinnoberinseln). Dort darf der
     * Wildfang-Eintrag also nicht wegfallen -- anders als bei der
     * Starterwahl, die die PokeAPI faelschlich als Encounter fuehrt.
     */
    public const STARTER_ALSO_WILD = ['lets-go-pikachu', 'lets-go-eevee'];

    /**
     * Fundorte, die die PokéAPI nicht liefert (spec.md 4).
     *
     * `location-area-encounters` deckt Karmesin/Purpur praktisch gar nicht ab
     * und kennt für viele Arten der Generationen 4 bis 8 nur leere Treffer.
     * `pokedex:fill-gaps` trägt für solche Arten deshalb einen Platzhalter
     * nach („Fundort noch nicht hinterlegt") – dieser wird hier durch von Hand
     * recherchierte Fundorte ersetzt (Serebii/Bulbapedia, deutsche Ortsnamen
     * teils gegen die PokéAPI abgeglichen).
     *
     * Probierert: Die Detailseite rendert Ortslinks zu PokéWiki nur aus
     * strukturierten `areas` – deshalb tragen die Einträge sie mit (sofern ein
     * fest benannter Ort existiert), den Rest erklärt `detail`.
     *
     * Endstufen ohne eigenen Fangweg stehen bewusst NICHT hier (siehe
     * EVOLUTIONS_LUECKEN); Versionsexklusive nur in der Edition, in der sie
     * auftauchen (das Gegenstück kümmert OHNE_FALLBACK).
     */
    public const ECHTE_FUNDORTE = [
        // ---- Sinnoh (Gen 4) ----
        'giratina' => [
            'diamond' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Höhle der Umkehr – ganz hinten, erst nach dem Nationaldex und Felssprenger',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Level 70. In Platin liegt der Weg stattdessen in der Welt des Verzerrten.',
                'areas' => [['slug' => 'turnback-cave', 'name_de' => 'Höhle der Umkehr']],
            ],
            'pearl' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Höhle der Umkehr – ganz hinten, erst nach dem Nationaldex und Felssprenger',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Level 70. In Platin liegt der Weg stattdessen in der Welt des Verzerrten.',
                'areas' => [['slug' => 'turnback-cave', 'name_de' => 'Höhle der Umkehr']],
            ],
        ],

        // ---- Einall (Gen 5) ----
        'basculin' => [
            'black' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Beim Angeln und Surfen in fast allen Gewässern Einalls',
                'difficulty' => Difficulty::Leicht,
                'areas' => [
                    ['slug' => 'route-1', 'name_de' => 'Route 1 (Einall)'],
                    ['slug' => 'route-3', 'name_de' => 'Route 3 (Einall)'],
                ],
            ],
            'white' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Beim Angeln und Surfen in fast allen Gewässern Einalls',
                'difficulty' => Difficulty::Leicht,
                'areas' => [
                    ['slug' => 'route-1', 'name_de' => 'Route 1 (Einall)'],
                    ['slug' => 'route-3', 'name_de' => 'Route 3 (Einall)'],
                ],
            ],
        ],
        'frillish' => [
            'black' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Beim Surfen auf Route 4, 17 und 18',
                'difficulty' => Difficulty::Leicht,
                'areas' => [['slug' => 'route-4', 'name_de' => 'Route 4 (Einall)']],
            ],
            'white' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Beim Surfen auf Route 4, 17 und 18',
                'difficulty' => Difficulty::Leicht,
                'areas' => [['slug' => 'route-4', 'name_de' => 'Route 4 (Einall)']],
            ],
        ],
        'tornadus' => [
            'black' => [
                'method' => ObtainMethod::Roaming,
                'detail' => 'Wandert nach der Story durch Einall – erste Begegnung auf Route 7',
                'difficulty' => Difficulty::Schwer,
                'note' => 'In Weiß nicht erhältlich, nur per Tausch aus Schwarz.',
                'areas' => [['slug' => 'route-7', 'name_de' => 'Route 7 (Einall)']],
            ],
        ],
        'thundurus' => [
            'white' => [
                'method' => ObtainMethod::Roaming,
                'detail' => 'Wandert nach der Story durch Einall – erste Begegnung auf Route 7',
                'difficulty' => Difficulty::Schwer,
                'note' => 'In Schwarz nicht erhältlich, nur per Tausch aus Weiß.',
                'areas' => [['slug' => 'route-7', 'name_de' => 'Route 7 (Einall)']],
            ],
        ],
        'landorus' => [
            'black' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Schrein der Ernte an Route 14 – erscheint, wenn Boreos und Voltolos im Team sind',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Level 70.',
                'areas' => [['slug' => 'abundant-shrine', 'name_de' => 'Schrein der Ernte']],
            ],
            'white' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Schrein der Ernte an Route 14 – erscheint, wenn Boreos und Voltolos im Team sind',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Level 70.',
                'areas' => [['slug' => 'abundant-shrine', 'name_de' => 'Schrein der Ernte']],
            ],
        ],

        // ---- Kalos (Gen 6) ----
        'pumpkaboo' => [
            'x' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Route 16 (hohes Gras)',
                'difficulty' => Difficulty::Leicht,
                'areas' => [['slug' => 'route-16', 'name_de' => 'Route 16 (Kalos)']],
            ],
            'y' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Route 16 (hohes Gras)',
                'difficulty' => Difficulty::Leicht,
                'areas' => [['slug' => 'route-16', 'name_de' => 'Route 16 (Kalos)']],
            ],
        ],
        'zygarde' => [
            'x' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Omega-Höhle an Route 18 – unterste Ebene',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Level 70.',
                'areas' => [['slug' => 'terminus-cave', 'name_de' => 'Omega-Höhle']],
            ],
            'y' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Omega-Höhle an Route 18 – unterste Ebene',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Level 70.',
                'areas' => [['slug' => 'terminus-cave', 'name_de' => 'Omega-Höhle']],
            ],
        ],

        // ---- Alola (Gen 7) ----
        'oricorio' => [
            'sun' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Baile-Stil im Mele-Mele-Blumenmeer; die anderen Stile auf den weiteren Inseln',
                'difficulty' => Difficulty::Leicht,
                'note' => 'Die Form hängt vom Fundort ab – Nektar wechselt den Stil.',
                'areas' => [['slug' => 'melemele-meadow', 'name_de' => 'Mele-Mele-Blumenmeer']],
            ],
            'moon' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Baile-Stil im Mele-Mele-Blumenmeer; die anderen Stile auf den weiteren Inseln',
                'difficulty' => Difficulty::Leicht,
                'note' => 'Die Form hängt vom Fundort ab – Nektar wechselt den Stil.',
                'areas' => [['slug' => 'melemele-meadow', 'name_de' => 'Mele-Mele-Blumenmeer']],
            ],
        ],
        'wishiwashi' => [
            'sun' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Beim Angeln an Stränden und Gewässern mehrerer Inseln – Blasen-Stellen erhöhen die Rate',
                'difficulty' => Difficulty::Leicht,
            ],
            'moon' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Beim Angeln an Stränden und Gewässern mehrerer Inseln – Blasen-Stellen erhöhen die Rate',
                'difficulty' => Difficulty::Leicht,
            ],
        ],
        'minior' => [
            'sun' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Hokulani-Berg (Gras und Felsen)',
                'difficulty' => Difficulty::Leicht,
                'note' => 'Die Kern-Farbe ist zufällig.',
                'areas' => [['slug' => 'mount-hokulani', 'name_de' => 'Hokulani-Berg']],
            ],
            'moon' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Hokulani-Berg (Gras und Felsen)',
                'difficulty' => Difficulty::Leicht,
                'note' => 'Die Kern-Farbe ist zufällig.',
                'areas' => [['slug' => 'mount-hokulani', 'name_de' => 'Hokulani-Berg']],
            ],
        ],
        'mimikyu' => [
            'sun' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Schnäppchenparadies (verlassener Laden auf Ula-Ula) – erst nach der Prüfung fangbar',
                'difficulty' => Difficulty::Mittel,
                'areas' => [['slug' => 'thrifty-megamart', 'name_de' => 'Schnäppchenparadies']],
            ],
            'moon' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Schnäppchenparadies (verlassener Laden auf Ula-Ula) – erst nach der Prüfung fangbar',
                'difficulty' => Difficulty::Mittel,
                'areas' => [['slug' => 'thrifty-megamart', 'name_de' => 'Schnäppchenparadies']],
            ],
        ],

        // ---- Galar (Gen 8) ----
        'eiscue' => [
            'sword' => [
                'method' => ObtainMethod::Raid,
                'detail' => 'Dyna-Raid in der Wildnis – in Schwert nicht wild anzutreffen',
                'difficulty' => Difficulty::Mittel,
                'note' => 'In Schild wild auf Route 10.',
            ],
            'shield' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Route 10 (Schneesturm) und Wutanfall-See',
                'difficulty' => Difficulty::Mittel,
                'areas' => [
                    ['slug' => 'route-10', 'name_de' => 'Route 10 (Galar)'],
                    ['slug' => 'lake-of-outrage', 'name_de' => 'Wutanfall-See'],
                ],
            ],
        ],
        'indeedee' => [
            'sword' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Wirrschein-Wald und Wutanfall-See – Männchen',
                'difficulty' => Difficulty::Mittel,
                'note' => 'In Schwert nur Männchen, in Schild nur Weibchen.',
                'areas' => [['slug' => 'glimwood-tangle', 'name_de' => 'Wirrschein-Wald']],
            ],
            'shield' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Wirrschein-Wald und Wutanfall-See – Weibchen',
                'difficulty' => Difficulty::Mittel,
                'note' => 'In Schwert nur Männchen, in Schild nur Weibchen.',
                'areas' => [['slug' => 'glimwood-tangle', 'name_de' => 'Wirrschein-Wald']],
            ],
        ],
        'morpeko' => [
            'sword' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Route 7, Route 9 und Wutanfall-See',
                'difficulty' => Difficulty::Mittel,
                'areas' => [['slug' => 'route-7', 'name_de' => 'Route 7 (Galar)']],
            ],
            'shield' => [
                'method' => ObtainMethod::Wild,
                'detail' => 'Route 7, Route 9 und Wutanfall-See',
                'difficulty' => Difficulty::Mittel,
                'areas' => [['slug' => 'route-7', 'name_de' => 'Route 7 (Galar)']],
            ],
        ],
        'kubfu' => [
            'sword' => [
                'method' => ObtainMethod::Gift,
                'detail' => 'Geschenk von Meister Musto im Meister-Dojo auf der Armor-Insel',
                'difficulty' => Difficulty::Mittel,
                'note' => 'Erweiterungspass (Armor-Insel) nötig.',
                'areas' => [['slug' => 'isle-of-armor', 'name_de' => 'Armor-Insel']],
            ],
            'shield' => [
                'method' => ObtainMethod::Gift,
                'detail' => 'Geschenk von Meister Musto im Meister-Dojo auf der Armor-Insel',
                'difficulty' => Difficulty::Mittel,
                'note' => 'Erweiterungspass (Armor-Insel) nötig.',
                'areas' => [['slug' => 'isle-of-armor', 'name_de' => 'Armor-Insel']],
            ],
        ],
        'regieleki' => [
            'shield' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Zwiespaltruinen in der Kronen-Schneelande (X-Muster)',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Regirock, Regice und Registeel müssen gefangen sein; nur eines der Regis pro Speicherstand. Schild-exklusiv, Erweiterungspass nötig.',
                'areas' => [['slug' => 'split-decision-ruins', 'name_de' => 'Zwiespaltruinen']],
            ],
        ],
        'regidrago' => [
            'sword' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Zwiespaltruinen in der Kronen-Schneelande (Y-Muster)',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Regirock, Regice und Registeel müssen gefangen sein; nur eines der Regis pro Speicherstand. Schwert-exklusiv, Erweiterungspass nötig.',
                'areas' => [['slug' => 'split-decision-ruins', 'name_de' => 'Zwiespaltruinen']],
            ],
        ],
        'glastrier' => [
            'sword' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Eiseroot-Karotte in der Schneeschlucht pflanzen, Fang am Kronentempel',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Nur eines der beiden Rosse pro Speicherstand.',
                'areas' => [
                    ['slug' => 'snowslide-slope', 'name_de' => 'Schneeschlucht'],
                    ['slug' => 'crown-shrine', 'name_de' => 'Kronentempel'],
                ],
            ],
            'shield' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Eiseroot-Karotte in der Schneeschlucht pflanzen, Fang am Kronentempel',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Nur eines der beiden Rosse pro Speicherstand.',
                'areas' => [
                    ['slug' => 'snowslide-slope', 'name_de' => 'Schneeschlucht'],
                    ['slug' => 'crown-shrine', 'name_de' => 'Kronentempel'],
                ],
            ],
        ],
        'spectrier' => [
            'sword' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Shaderoot-Karotte auf dem Uralten Friedhof pflanzen, Fang am Kronentempel',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Nur eines der beiden Rosse pro Speicherstand.',
                'areas' => [
                    ['slug' => 'old-cemetery', 'name_de' => 'Uralter Friedhof'],
                    ['slug' => 'crown-shrine', 'name_de' => 'Kronentempel'],
                ],
            ],
            'shield' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Shaderoot-Karotte auf dem Uralten Friedhof pflanzen, Fang am Kronentempel',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Nur eines der beiden Rosse pro Speicherstand.',
                'areas' => [
                    ['slug' => 'old-cemetery', 'name_de' => 'Uralter Friedhof'],
                    ['slug' => 'crown-shrine', 'name_de' => 'Kronentempel'],
                ],
            ],
        ],
        'calyrex' => [
            'sword' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Kronentempel nach Ende der Legenden-Forschung',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Erweiterungspass (Kronen-Schneelande) nötig.',
                'areas' => [['slug' => 'crown-shrine', 'name_de' => 'Kronentempel']],
            ],
            'shield' => [
                'method' => ObtainMethod::StaticEncounter,
                'detail' => 'Kronentempel nach Ende der Legenden-Forschung',
                'difficulty' => Difficulty::Schwer,
                'note' => 'Erweiterungspass (Kronen-Schneelande) nötig.',
                'areas' => [['slug' => 'crown-shrine', 'name_de' => 'Kronentempel']],
            ],
        ],

        // ---- Paldea (Gen 9) ----
        'lechonk' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Häufiger Wildfang im Grasland der Südlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Häufiger Wildfang im Grasland der Südlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'oinkologne' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Südlichen und Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Südlichen und Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'tarountula' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Wald und Grasland der Südlichen und Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Wald und Grasland der Südlichen und Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'spidops' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Westlichen und Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Westlichen und Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'nymble' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland der Westlichen und Südlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland der Westlichen und Südlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'lokix' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Nördlichen Provinz und am Montanata – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Nördlichen Provinz und am Montanata – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'pawmi' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland der Südlichen, Westlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland der Südlichen, Westlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'pawmo' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland der Südlichen und Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland der Südlichen und Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'tandemaus' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang nahe Ortschaften (Östliche und Westliche Provinz)', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang nahe Ortschaften (Östliche und Westliche Provinz)', 'difficulty' => Difficulty::Mittel],
        ],
        'fidough' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Ortschaften und auf umliegenden Feldern', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Ortschaften und auf umliegenden Feldern', 'difficulty' => Difficulty::Leicht],
        ],
        'dachsbun' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang nahe Ortschaften – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang nahe Ortschaften – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'smoliv' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf Blumenwiesen der Südlichen und Nördlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf Blumenwiesen der Südlichen und Nördlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'dolliv' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf Blumenwiesen der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf Blumenwiesen der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'squawkabilly' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Ortschaften und Parks', 'difficulty' => Difficulty::Leicht, 'note' => 'Die vier Farbformen verteilen sich regional unterschiedlich.'],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Ortschaften und Parks', 'difficulty' => Difficulty::Leicht, 'note' => 'Die vier Farbformen verteilen sich regional unterschiedlich.'],
        ],
        'nacli' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten und Felsflächen der Südlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten und Felsflächen der Südlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'naclstack' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten der Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten der Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'charcadet' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Höhlen- und Waldflächen der Östlichen Provinz – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Höhlen- und Waldflächen der Östlichen Provinz – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'tadbulb' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Flussläufen der Südlichen und Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Flussläufen der Südlichen und Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'bellibolt' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Flussläufen und Seen in der Südlichen, Östlichen, Westlichen und Nördlichen Provinz – sehr selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Flussläufen und Seen in der Südlichen, Östlichen, Westlichen und Nördlichen Provinz – sehr selten', 'difficulty' => Difficulty::Mittel],
        ],
        'wattrel' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küstenabschnitten der Südlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küstenabschnitten der Südlichen und Östlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'kilowattrel' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten der Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten der Östlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'maschiff' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland nahe Ortschaften', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland nahe Ortschaften', 'difficulty' => Difficulty::Leicht],
        ],
        'mabosstiff' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang nahe Ortschaften – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang nahe Ortschaften – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'shroodle' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Wäldern der Südlichen, Östlichen und Westlichen Provinz', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Wäldern der Südlichen, Östlichen und Westlichen Provinz', 'difficulty' => Difficulty::Mittel],
        ],
        'bramblin' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf trockenen Flächen und im Bambuswald der Nördlichen Provinz', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf trockenen Flächen und im Bambuswald der Nördlichen Provinz', 'difficulty' => Difficulty::Mittel],
        ],
        'brambleghast' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Bambuswald der Nördlichen Provinz – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Bambuswald der Nördlichen Provinz – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'toedscool' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Wäldern und im Grasland fast überall', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Wäldern und im Grasland fast überall', 'difficulty' => Difficulty::Leicht],
        ],
        'klawf' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Felsflächen der Südlichen Provinz – auch als Titan', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Felsflächen der Südlichen Provinz – auch als Titan', 'difficulty' => Difficulty::Mittel],
        ],
        'capsakid' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Wüste der Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Wüste der Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'rellor' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Wüste der Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Wüste der Westlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'scovillain' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen und in der Wüste – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen und in der Wüste – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'flittle' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen Provinz', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen Provinz', 'difficulty' => Difficulty::Leicht],
        ],
        'espathra' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'tinkatink' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Berggebieten der Nördlichen Provinz und am Montanata', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Berggebieten der Nördlichen Provinz und am Montanata', 'difficulty' => Difficulty::Mittel],
        ],
        'tinkatuff' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Berggebieten der Nördlichen Provinz – selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Berggebieten der Nördlichen Provinz – selten', 'difficulty' => Difficulty::Schwer],
        ],
        'wiglett' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Stränden und im Sand fast aller Küsten', 'difficulty' => Difficulty::Leicht],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Stränden und im Sand fast aller Küsten', 'difficulty' => Difficulty::Leicht],
        ],
        'wugtrio' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Küsten der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'bombirdier' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Klippen und Stränden der Westlichen Provinz – auch als Titan', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Klippen und Stränden der Westlichen Provinz – auch als Titan', 'difficulty' => Difficulty::Mittel],
        ],
        'finizen' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Meer und in großen Seen', 'difficulty' => Difficulty::Leicht, 'note' => 'Delfinator entsteht nur im Union Circle, nicht durch Levelaufstieg.'],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Meer und in großen Seen', 'difficulty' => Difficulty::Leicht, 'note' => 'Delfinator entsteht nur im Union Circle, nicht durch Levelaufstieg.'],
        ],
        'varoom' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Industriegebieten und der Östlichen Zone (Gebiet 3)', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Industriegebieten und der Östlichen Zone (Gebiet 3)', 'difficulty' => Difficulty::Mittel],
        ],
        'cyclizar' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland nahe Ortschaften – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Grasland nahe Ortschaften – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'orthworm' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Wüste und in Höhlen – auch als Titan', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Wüste und in Höhlen – auch als Titan', 'difficulty' => Difficulty::Mittel],
        ],
        'glimmet' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Höhlenwänden der Südlichen Provinz – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Höhlenwänden der Südlichen Provinz – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'greavard' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen, Östlichen und Nördlichen Provinz bei Nacht', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen, Östlichen und Nördlichen Provinz bei Nacht', 'difficulty' => Difficulty::Mittel],
        ],
        'houndstone' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Berghängen der Südlichen Provinz – selten', 'difficulty' => Difficulty::Mittel],
        ],
        'flamigo' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Gewässerufern und in der Südlichen Provinz', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang an Gewässerufern und in der Südlichen Provinz', 'difficulty' => Difficulty::Mittel],
        ],
        'cetoddle' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Schneegebiet der Nördlichen Provinz und am Montanata', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Schneegebiet der Nördlichen Provinz und am Montanata', 'difficulty' => Difficulty::Mittel],
        ],
        'veluza' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang beim Tauchen in Küstengewässern (Südliche und Westliche Provinz)', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang beim Tauchen in Küstengewässern (Südliche und Westliche Provinz)', 'difficulty' => Difficulty::Mittel],
        ],
        'dondozo' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Caldero-See und im Ozean – auch als Titan', 'difficulty' => Difficulty::Mittel],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Caldero-See und im Ozean – auch als Titan', 'difficulty' => Difficulty::Mittel],
        ],
        'tatsugiri' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Caldero-See und im Ozean – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang im Caldero-See und im Ozean – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'frigibax' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Höhlen und Schneegebieten der Nördlichen Provinz und am Montanata – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in Höhlen und Schneegebieten der Nördlichen Provinz und am Montanata – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'arctibax' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in einer Höhle am Montanata – sehr selten', 'difficulty' => Difficulty::Schwer],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in einer Höhle am Montanata – sehr selten', 'difficulty' => Difficulty::Schwer],
        ],
        'gimmighoul' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Truhenform an Wachtürmen, Ruinen und abgelegenen Orten', 'difficulty' => Difficulty::Leicht, 'note' => 'Monetigo entsteht aus Gierspenst mit 999 Münzen auf Level 50.'],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Truhenform an Wachtürmen, Ruinen und abgelegenen Orten', 'difficulty' => Difficulty::Leicht, 'note' => 'Monetigo entsteht aus Gierspenst mit 999 Münzen auf Level 50.'],
        ],

        // ---- Paradoxe Arten und Legenden (Gen 9, Zone Null) ----
        'great-tusk' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Untergrund und Hochplateaus)', 'difficulty' => Difficulty::Mittel, 'note' => 'In Karmesin auch als Titan im Reservat der Wüste Asado.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'scream-tail' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Höhlen und Untergrund)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'brute-bonnet' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Untergrund)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'flutter-mane' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Höhlen und Untergrund)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'slither-wing' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Höhleneingänge)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'sandy-shocks' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Hochplateaus)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'roaring-moon' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null – versteckte Höhle, sehr selten', 'difficulty' => Difficulty::Schwer, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-treads' => [
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Hochplateaus)', 'difficulty' => Difficulty::Mittel, 'note' => 'In Purpur auch als Titan im Reservat der Wüste Asado.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-bundle' => [
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Höhlen und Untergrund)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-hands' => [
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Untergrund)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-jugulis' => [
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Höhlen und Untergrund)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-moth' => [
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Hochplateaus)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-thorns' => [
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null (Höhleneingänge)', 'difficulty' => Difficulty::Mittel, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-valiant' => [
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang in der Zone Null – versteckte Höhle, sehr selten', 'difficulty' => Difficulty::Schwer, 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'koraidon' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Story: Reittier ab Spielbeginn, fangbar nach „Der Weg nach Hause“ in der Zone Null', 'difficulty' => Difficulty::Mittel, 'note' => 'Karmesin-exklusiv; ein Exemplar pro Speicherstand.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'miraidon' => [
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Story: Reittier ab Spielbeginn, fangbar nach „Der Weg nach Hause“ in der Zone Null', 'difficulty' => Difficulty::Mittel, 'note' => 'Purpur-exklusiv; ein Exemplar pro Speicherstand.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'wo-chien' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein des modrigen Holzes – die 8 violetten Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-wo-chien', 'name_de' => 'Schrein des modrigen Holzes']]],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein des modrigen Holzes – die 8 violetten Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-wo-chien', 'name_de' => 'Schrein des modrigen Holzes']]],
        ],
        'chien-pao' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein des entzweiten Eises – die 8 gelben Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-chien-pao', 'name_de' => 'Schrein des entzweiten Eises']]],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein des entzweiten Eises – die 8 gelben Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-chien-pao', 'name_de' => 'Schrein des entzweiten Eises']]],
        ],
        'ting-lu' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein der unreinen Erde – die 8 grünen Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-ting-lu', 'name_de' => 'Schrein der unreinen Erde']]],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein der unreinen Erde – die 8 grünen Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-ting-lu', 'name_de' => 'Schrein der unreinen Erde']]],
        ],
        'chi-yu' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein der finsteren Flamme – die 8 blauen Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-chi-yu', 'name_de' => 'Schrein der finsteren Flamme']]],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Schrein der finsteren Flamme – die 8 blauen Unheilspfähle ziehen, dann Kampf auf Level 60', 'difficulty' => Difficulty::Schwer, 'note' => 'Langes Legenden-Rätsel.', 'areas' => [['slug' => 'shrine-of-chi-yu', 'name_de' => 'Schrein der finsteren Flamme']]],
        ],
        'walking-wake' => [
            'scarlet' => ['method' => ObtainMethod::Event, 'detail' => '5-Sterne-Tera-Raid – nur während des limitierten Raid-Events', 'difficulty' => Difficulty::SehrSchwer, 'event_expired' => true, 'note' => 'Raid-Event ist vorbei – nur noch über Tausch erreichbar.'],
        ],
        'iron-leaves' => [
            'violet' => ['method' => ObtainMethod::Event, 'detail' => '5-Sterne-Tera-Raid – nur während des limitierten Raid-Events', 'difficulty' => Difficulty::SehrSchwer, 'event_expired' => true, 'note' => 'Raid-Event ist vorbei – nur noch über Tausch erreichbar.'],
        ],
        'poltchageist' => [
            'scarlet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf Kitakami (Apfelhügel, Pfad des Tanzes) – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Leicht, 'note' => 'Erweiterungspass nötig.'],
            'violet' => ['method' => ObtainMethod::Wild, 'detail' => 'Wildfang auf Kitakami (Apfelhügel, Pfad des Tanzes) – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Leicht, 'note' => 'Erweiterungspass nötig.'],
        ],
        'okidogi' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung auf der Paradiesebene (Kitakami) nach der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; Stufe 70.'],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung auf der Paradiesebene (Kitakami) nach der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; Stufe 70.'],
        ],
        'munkidori' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung in den Wisterienfeldern (Kitakami) nach der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; Stufe 70.'],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung in den Wisterienfeldern (Kitakami) nach der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; Stufe 70.'],
        ],
        'fezandipiti' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung am Onia-Berg (Kitakami) nach der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; Stufe 70.'],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung am Onia-Berg (Kitakami) nach der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; Stufe 70.'],
        ],
        'ogerpon' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung am Onia-Berg am Ende der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; die Masken werden später einzeln erworben.'],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Begegnung am Onia-Berg am Ende der Story – DLC Die Türkisgrüne Maske', 'difficulty' => Difficulty::Mittel, 'note' => 'Erweiterungspass nötig; die Masken werden später einzeln erworben.'],
        ],
        'gouging-fire' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Zone Null – auf einem Wasserfall, erst nach Perrins Quest (DLC Die Indigoblaue Scheibe)', 'difficulty' => Difficulty::SehrSchwer, 'note' => 'Karmesin-exklusiv; Erweiterungspass und 200 Blaubeer-Dex-Einträge nötig.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'raging-bolt' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Zone Null – hinter Forschungsstation 3, erst nach Perrins Quest (DLC Die Indigoblaue Scheibe)', 'difficulty' => Difficulty::SehrSchwer, 'note' => 'Karmesin-exklusiv; Erweiterungspass und 200 Blaubeer-Dex-Einträge nötig.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-boulder' => [
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Zone Null – hinter einer eingestürzten Felswand am Fluss, erst nach Perrins Quest (DLC Die Indigoblaue Scheibe)', 'difficulty' => Difficulty::SehrSchwer, 'note' => 'Purpur-exklusiv; Erweiterungspass und 200 Blaubeer-Dex-Einträge nötig.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'iron-crown' => [
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Zone Null – in einer Felsnische unterhalb des Eingangs, erst nach Perrins Quest (DLC Die Indigoblaue Scheibe)', 'difficulty' => Difficulty::SehrSchwer, 'note' => 'Purpur-exklusiv; Erweiterungspass und 200 Blaubeer-Dex-Einträge nötig.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
        'terapagos' => [
            'scarlet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Zone Null – unter dem Null-Labor, Ende der Story von Die Indigoblaue Scheibe', 'difficulty' => Difficulty::Schwer, 'note' => 'Erweiterungspass nötig.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
            'violet' => ['method' => ObtainMethod::StaticEncounter, 'detail' => 'Zone Null – unter dem Null-Labor, Ende der Story von Die Indigoblaue Scheibe', 'difficulty' => Difficulty::Schwer, 'note' => 'Erweiterungspass nötig.', 'areas' => [['slug' => 'area-zero', 'name_de' => 'Zone Null']]],
        ],
    ];

    /**
     * Endstufen ohne eigenen Fangweg, deren Lücken-Platzhalter nur irreführt.
     *
     * Sie stehen nicht in ECHTE_FUNDORTE, weil ihr weg der Evolution ist – nach
     * `pokedex:recalculate` zeigen sie „Entwicklung aus …" statt eines
     * erfundenen Wildfangs. Erst gedeckt durch ihre Basis (z.B. Apoquallyp via
     * Quabbel, Maskagato via Felori).
     */
    public const EVOLUTIONS_LUECKEN = [
        'jellicent', 'gourgeist', 'urshifu',
        'floragato', 'meowscarada', 'crocalor', 'skeledirge', 'quaxwell', 'quaquaval',
        'arboliva', 'garganacl', 'pawmot', 'maushold', 'armarouge', 'ceruledge',
        'revavroom', 'toedscruel', 'grafaiai', 'tinkaton', 'glimmora', 'cetitan',
        'palafin', 'gholdengo', 'sinistcha',
        'baxcalibur', 'rabsca',
    ];

    /**
     * Versionsexklusive, die im Gegenstück gar nicht vorkommen.
     *
     * pokemon-slug => [game-slugs, in denen der Lücken-Platzhalter entfernt
     * wird]. Die Einträge der richtigen Edition stehen in ECHTE_FUNDORTE.
     */
    public const OHNE_FALLBACK = [
        'tornadus' => ['white'],
        'thundurus' => ['black'],
        'regieleki' => ['sword'],
        'regidrago' => ['shield'],
        'koraidon' => ['violet'],
        'miraidon' => ['scarlet'],
        'walking-wake' => ['violet'],
        'iron-leaves' => ['scarlet'],
        'great-tusk' => ['violet'],
        'scream-tail' => ['violet'],
        'brute-bonnet' => ['violet'],
        'flutter-mane' => ['violet'],
        'slither-wing' => ['violet'],
        'sandy-shocks' => ['violet'],
        'roaring-moon' => ['violet'],
        'iron-treads' => ['scarlet'],
        'iron-bundle' => ['scarlet'],
        'iron-hands' => ['scarlet'],
        'iron-jugulis' => ['scarlet'],
        'iron-moth' => ['scarlet'],
        'iron-thorns' => ['scarlet'],
        'iron-valiant' => ['scarlet'],
        'gouging-fire' => ['violet'],
        'raging-bolt' => ['violet'],
        'iron-boulder' => ['scarlet'],
        'iron-crown' => ['scarlet'],
    ];

    public function run(): void
    {
        $games = Game::query()->pluck('id', 'slug');
        $pokemon = Pokemon::query()->pluck('id', 'slug');

        // Ohne diesen Hinweis bliebe es unbemerkt, wenn der Seeder vor dem
        // Import läuft: er findet dann keine Art und legt stillschweigend
        // nichts an – Starter und Fossilien fehlten danach als Bezugsquelle.
        if ($pokemon->isEmpty() && $this->command !== null) {
            $this->command->warn(
                'CuratedObtainabilitySeeder: keine Pokémon in der Datenbank – '
                .'nach `pokedex:import` erneut ausführen.'
            );

            return;
        }

        $this->seedGroup(self::STARTERS, $games, $pokemon, ObtainMethod::Gift, Difficulty::Leicht, 'Starter-Pokémon zu Spielbeginn');
        $this->seedGroup(self::FOSSILS, $games, $pokemon, ObtainMethod::Fossil, Difficulty::Mittel, 'Fossil wiederbeleben');
        $this->seedGroup(
            self::GIFTS, $games, $pokemon, ObtainMethod::Gift, Difficulty::Leicht,
            'Geschenk in Freezington (Kronen-Schneelande)',
            'Setzt den Erweiterungspass voraus.',
        );

        $this->seedGroup(
            self::ORAS_GIFT_JOHTO, $games, $pokemon, ObtainMethod::Gift, Difficulty::Leicht,
            'Geschenk von Prof. Birk nach dem Ligasieg',
            'Eines der drei Johto-Starter, nach dem Ligasieg und dem Treffen mit Amara.',
        );
        $this->seedGroup(
            self::ORAS_GIFT_UNOVA, $games, $pokemon, ObtainMethod::Gift, Difficulty::Leicht,
            'Geschenk von Prof. Birk nach der Delta-Episode',
            'Eines der drei Einall-Starter, nach Abschluss der Delta-Episode.',
        );
        $this->seedGroup(
            self::ORAS_GIFT_SINNOH, $games, $pokemon, ObtainMethod::Gift, Difficulty::Leicht,
            'Geschenk von Prof. Birk nach dem zweiten Ligasieg',
            'Eines der drei Sinnoh-Starter, nach dem zweiten Einzug in die Ruhmeshalle.',
        );

        $this->seedGroup(
            self::HISUI_EXCLUSIVE, $games, $pokemon, ObtainMethod::Wild, Difficulty::Mittel,
            'In Hisui zu fangen',
        );

        $this->seedDynamaxAbenteuer($games, $pokemon);
        $this->seedEchteFundorte($games, $pokemon);

        $this->entferneStarterwahlAlsWildfang($games, $pokemon);
        $this->entferneFalscheHisuiFallbacks($games, $pokemon);
        $this->entferneBehandelteLuecken($games, $pokemon);

        foreach (self::EXPIRED_EVENT_MYTHICALS as $slug) {
            $pokemonId = $pokemon[$slug] ?? null;

            if ($pokemonId === null) {
                continue;
            }

            // Ohne Spielbezug – die Verteilung lief über mehrere Titel hinweg.
            // Der Eintrag hängt am ältesten Spiel der jeweiligen Generation.
            $gameId = $games['diamond'] ?? $games->first();

            Obtainability::updateOrCreate(
                [
                    'pokemon_id' => $pokemonId,
                    'game_id' => $gameId,
                    'method' => ObtainMethod::Event->value,
                ],
                [
                    'pokemon_form_id' => null,
                    'location_detail' => 'Zeitlich begrenzte Verteilung',
                    'difficulty' => Difficulty::SehrSchwer->value,
                    'event_expired' => true,
                    'note' => 'Event ist vorbei – nur noch über Tausch/Community erreichbar.',
                    'source' => 'curated',
                ],
            );
        }
    }

    /** @param  array<string,array<int,string>>  $group */
    private function seedGroup(
        array $group,
        $games,
        $pokemon,
        ObtainMethod $method,
        Difficulty $difficulty,
        string $detail,
        ?string $note = null,
    ): void {
        foreach ($group as $slug => $gameSlugs) {
            $pokemonId = $pokemon[$slug] ?? null;

            if ($pokemonId === null) {
                continue;
            }

            foreach ($gameSlugs as $gameSlug) {
                $gameId = $games[$gameSlug] ?? null;

                if ($gameId === null) {
                    continue;
                }

                Obtainability::updateOrCreate(
                    [
                        'pokemon_id' => $pokemonId,
                        'game_id' => $gameId,
                        'method' => $method->value,
                    ],
                    [
                        'pokemon_form_id' => null,
                        'location_detail' => $detail,
                        'difficulty' => $difficulty->value,
                        'event_expired' => false,
                        'note' => $note,
                        'source' => 'curated',
                    ],
                );
            }
        }
    }

    /**
     * Entfernt die Starterwahl, die als Wildfang in den Daten steht.
     *
     * Die PokeAPI fuehrt die Uebergabe des Starters als regulaeren Encounter im
     * jeweiligen Startort -- Chelast steht dadurch mit "Wildfang, Lake Verity"
     * in Diamant, obwohl es dort niemand fangen kann. Neben dem kuratierten
     * Geschenk-Eintrag steht damit eine zweite Zeile, die dasselbe Ereignis
     * falsch benennt.
     *
     * Erkennungsmerkmal ist der *eine* Fundort: Die Starterwahl passiert an
     * genau einem Ort. Wo ein Starter wirklich wild vorkommt, nennt die
     * PokeAPI mehrere Gebiete (Let's Go) -- solche Zeilen bleiben, ebenso
     * jedes Spiel aus STARTER_ALSO_WILD.
     *
     * Der naechste `pokedex:import-encounters` legt die Zeilen wieder an; der
     * Seeder laeuft laut README danach und raeumt sie erneut weg.
     */
    private function entferneStarterwahlAlsWildfang($games, $pokemon): void
    {
        $entfernt = 0;

        $gruppen = array_merge_recursive(
            self::STARTERS,
            self::ORAS_GIFT_JOHTO,
            self::ORAS_GIFT_UNOVA,
            self::ORAS_GIFT_SINNOH,
        );

        foreach ($gruppen as $slug => $gameSlugs) {
            $pokemonId = $pokemon[$slug] ?? null;

            if ($pokemonId === null) {
                continue;
            }

            foreach ($gameSlugs as $gameSlug) {
                $gameId = $games[$gameSlug] ?? null;

                if ($gameId === null || in_array($gameSlug, self::STARTER_ALSO_WILD, true)) {
                    continue;
                }

                $entfernt += Obtainability::query()
                    ->where('pokemon_id', $pokemonId)
                    ->where('game_id', $gameId)
                    ->where('method', ObtainMethod::Wild->value)
                    ->whereNull('pokemon_form_id')
                    ->where('location_detail', 'not like', '%,%')
                    ->delete();
            }
        }

        if ($entfernt > 0 && $this->command !== null) {
            $this->command->info(
                "CuratedObtainabilitySeeder: {$entfernt} als Wildfang gefuehrte Starterwahlen entfernt "
                .'-- sie stehen als Geschenk in der Liste.'
            );
        }
    }

    /**
     * Legendaere aus den Dyna-Raids, an beiden Editionen.
     *
     * Eigene Methode statt seedGroup, weil die Liste flach ist: dieselben Arten,
     * dieselben zwei Spiele.
     */
    private function seedDynamaxAbenteuer($games, $pokemon): void
    {
        $notiz = 'Erweiterungspass noetig. Die Versionsbindung gilt nur beim eigenen Hosten '
            .'-- wer bei anderen mitgeht, trifft auch die Legendaeren der anderen Edition.';

        foreach (self::DYNAMAX_ADVENTURES as $slug) {
            $pokemonId = $pokemon[$slug] ?? null;

            if ($pokemonId === null) {
                continue;
            }

            foreach (['sword', 'shield'] as $gameSlug) {
                $gameId = $games[$gameSlug] ?? null;

                if ($gameId === null) {
                    continue;
                }

                Obtainability::updateOrCreate(
                    [
                        'pokemon_id' => $pokemonId,
                        'game_id' => $gameId,
                        'method' => ObtainMethod::Raid->value,
                    ],
                    [
                        'pokemon_form_id' => null,
                        'location_detail' => 'Dyna-Raid im Max-Lager (Kronen-Schneelande)',
                        'difficulty' => Difficulty::Mittel->value,
                        'event_expired' => false,
                        'note' => $notiz,
                        'source' => 'curated',
                    ],
                );
            }
        }
    }

    /**
     * Raeumt die Hisui-Arten aus Schwert/Schild, wo `pokedex:fill-gaps` sie
     * mangels Alternative hingehaengt hatte.
     *
     * Nur die eigenen Fallback-Zeilen werden angefasst; echte Fundorte aus der
     * PokeAPI bleiben unberuehrt -- falls eine dieser Arten spaeter doch in
     * einem Galar-Titel auftaucht, steht sie weiter da.
     */
    private function entferneFalscheHisuiFallbacks($games, $pokemon): void
    {
        $galar = array_filter([$games['sword'] ?? null, $games['shield'] ?? null]);

        if ($galar === []) {
            return;
        }

        $ids = array_filter(array_map(
            fn (string $slug) => $pokemon[$slug] ?? null,
            array_keys(self::HISUI_EXCLUSIVE),
        ));

        if ($ids === []) {
            return;
        }

        $entfernt = Obtainability::query()
            ->whereIn('pokemon_id', $ids)
            ->whereIn('game_id', $galar)
            ->where('source', 'generation-fallback')
            ->delete();

        if ($entfernt > 0 && $this->command !== null) {
            $this->command->info(
                "CuratedObtainabilitySeeder: {$entfernt} Hisui-Arten aus Schwert/Schild entfernt "
                .'-- dort kommen sie nicht vor.'
            );
        }
    }

    /**
     * Trägt die recherchierten Fundorte ein (siehe ECHTE_FUNDORTE).
     *
     * Aktualisiert an (Pokémon, Spiel, Methode): Eine bestehende
     * Wildfang-Lücke wird damit an Ort und Stelle zum echten Fundort, statt
     * eine zweite Zeile anzulegen. Die `locations` werden mit den strukturierten
     * Gebieten gefüllt, damit die Detailseite PokéWiki-Links rendern kann.
     */
    private function seedEchteFundorte($games, $pokemon): void
    {
        foreach (self::ECHTE_FUNDORTE as $slug => $spiele) {
            $pokemonId = $pokemon[$slug] ?? null;

            if ($pokemonId === null) {
                continue;
            }

            foreach ($spiele as $gameSlug => $fundort) {
                $gameId = $games[$gameSlug] ?? null;

                if ($gameId === null) {
                    continue;
                }

                Obtainability::updateOrCreate(
                    [
                        'pokemon_id' => $pokemonId,
                        'game_id' => $gameId,
                        'method' => $fundort['method']->value,
                    ],
                    [
                        'pokemon_form_id' => null,
                        'location_detail' => $fundort['detail'],
                        'locations' => ($fundort['areas'] ?? [])
                            ? ['areas' => $fundort['areas'], 'truncated' => false]
                            : null,
                        'difficulty' => $fundort['difficulty']->value,
                        'event_expired' => $fundort['event_expired'] ?? false,
                        'note' => $fundort['note'] ?? null,
                        'source' => 'curated',
                    ],
                );
            }
        }
    }

    /**
     * Räumt die Platzhalter der behandelten Arten weg.
     *
     * Drei Gruppen: Endstufen ohne eigenen Fangweg (EVOLUTIONS_LUECKEN – nach
     * dem Recalculate zeigen sie „Entwicklung aus …“), Versionsexklusive im
     * Gegenstück (OHNE_FALLBACK) sowie Lücken, deren Methode sich vom echten
     * Fundort unterscheidet (z.B. Statik statt Wildfang).
     *
     * Nur die eigenen Fallback-Zeilen werden angefasst; echte Fundorte bleiben
     * unberührt.
     */
    private function entferneBehandelteLuecken($games, $pokemon): void
    {
        $behandelt = array_unique(array_merge(
            array_keys(self::ECHTE_FUNDORTE),
            array_keys(array_flip(self::EVOLUTIONS_LUECKEN)),
            array_keys(self::OHNE_FALLBACK),
        ));

        $ids = array_filter(array_map(
            fn (string $slug) => $pokemon[$slug] ?? null,
            $behandelt,
        ));

        if ($ids === []) {
            return;
        }

        $slugZuId = $pokemon->flip();
        $gameSlugZuId = $games->flip();

        $entfernt = 0;

        $luecken = Obtainability::query()
            ->whereIn('pokemon_id', $ids)
            ->where('source', 'generation-fallback')
            ->get();

        $luecken->each(function (Obtainability $zeile) use (&$entfernt, $slugZuId, $gameSlugZuId) {
            $slug = $slugZuId[$zeile->pokemon_id] ?? null;
            $gameSlug = $gameSlugZuId[$zeile->game_id] ?? null;

            // Endstufen: Lücke in jeder Edition entfernen.
            if (in_array($slug, self::EVOLUTIONS_LUECKEN, true)) {
                $entfernt += $zeile->delete();

                return;
            }

            // Versionsexklusive: nur im Gegenstück entfernen.
            if (isset(self::OHNE_FALLBACK[$slug])
                && in_array($gameSlug, self::OHNE_FALLBACK[$slug], true)) {
                $entfernt += $zeile->delete();

                return;
            }

            // Echte Fundorte: übrige Lücken sind die, die kein echter
            // Eintrag abdeckt (andere Methode oder andere Edition).
            if (isset(self::ECHTE_FUNDORTE[$slug])) {
                $entfernt += $zeile->delete();
            }
        });

        if ($entfernt > 0 && $this->command !== null) {
            $this->command->info(
                "CuratedObtainabilitySeeder: {$entfernt} Platzhalter entfernt "
                .'-- Arten mit recherchiertem Fundort bzw. Evolution statt Wildfang.'
            );
        }
    }
}
