/**
 * AHG Voice Command Translations — Multilingual command patterns
 *
 * Maps English command patterns to equivalent patterns in supported languages.
 * Each language object maps an English pattern string to an array of translated alternatives.
 * Only string patterns are translated (regex patterns remain English-only).
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
var AHGVoiceTranslations = (function () {
  'use strict';

  var translations = {

    // ── Afrikaans ──────────────────────────────────────────────────────────
    'af-ZA': {
      // Navigation
      'go home': ['gaan huis toe', 'tuisblad'],
      'home': ['huis', 'tuis'],
      'homepage': ['tuisblad'],
      'browse': ['blaai', 'blaai deur'],
      'go to admin': ['gaan na admin', 'administrasie'],
      'admin': ['admin', 'administrasie'],
      'admin panel': ['admin paneel'],
      'go to settings': ['gaan na instellings'],
      'settings': ['instellings'],
      'go to clipboard': ['gaan na knipbord'],
      'clipboard': ['knipbord'],
      'go back': ['gaan terug', 'terug'],
      'back': ['terug'],
      'previous page': ['vorige bladsy'],
      'next page': ['volgende bladsy'],
      'go to next page': ['gaan na volgende bladsy'],
      'go to previous page': ['gaan na vorige bladsy'],
      'prev page': ['vorige'],
      'go to donors': ['gaan na skenkers'],
      'donors': ['skenkers'],
      'browse donors': ['blaai skenkers'],
      'go to research': ['gaan na navorsing'],
      'research': ['navorsing'],
      'reading room': ['leeskamer'],
      'go to authorities': ['gaan na outoriteite'],
      'authorities': ['outoriteite'],
      'browse authorities': ['blaai outoriteite'],
      'authority records': ['outoriteitsrekords'],
      'go to places': ['gaan na plekke'],
      'places': ['plekke'],
      'browse places': ['blaai plekke'],
      'go to subjects': ['gaan na onderwerpe'],
      'subjects': ['onderwerpe'],
      'browse subjects': ['blaai onderwerpe'],
      'go to digital objects': ['gaan na digitale voorwerpe'],
      'digital objects': ['digitale voorwerpe'],
      'browse digital objects': ['blaai digitale voorwerpe'],
      'browse archive': ['blaai argief'],
      'browse archives': ['blaai argiewe'],
      'go to archives': ['gaan na argiewe'],
      'browse library': ['blaai biblioteek'],
      'go to library': ['gaan na biblioteek'],
      'library records': ['biblioteekrekords'],
      'browse museum': ['blaai museum'],
      'go to museum': ['gaan na museum'],
      'museum records': ['museumrekords'],
      'browse gallery': ['blaai galery'],
      'go to gallery': ['gaan na galery'],
      'gallery records': ['galeryrekords'],
      'go to accessions': ['gaan na aanwinste'],
      'accessions': ['aanwinste'],
      'browse accessions': ['blaai aanwinste'],
      'go to repositories': ['gaan na bewaarplekke'],
      'repositories': ['bewaarplekke'],
      'institutions': ['instellings'],
      'browse repositories': ['blaai bewaarplekke'],

      // Actions: Edit
      'save': ['stoor', 'bewaar'],
      'save record': ['stoor rekord', 'bewaar rekord'],
      'save this': ['stoor dit'],
      'cancel': ['kanselleer'],
      'cancel edit': ['kanselleer wysiging'],
      'delete': ['verwyder', 'skrap'],
      'delete record': ['verwyder rekord', 'skrap rekord'],
      'delete this': ['verwyder dit'],

      // Actions: View
      'edit': ['wysig', 'redigeer'],
      'edit record': ['wysig rekord'],
      'edit this': ['wysig dit'],
      'print': ['druk'],
      'print page': ['druk bladsy'],
      'print this': ['druk dit'],
      'export csv': ['voer uit csv'],
      'export to csv': ['voer uit na csv'],

      // Actions: Browse
      'first result': ['eerste resultaat'],
      'open first': ['maak eerste oop'],
      'sort by title': ['sorteer volgens titel'],
      'sort by date': ['sorteer volgens datum'],

      // Global
      'scroll down': ['blaai af', 'rol af'],
      'scroll up': ['blaai op', 'rol op'],
      'scroll to top': ['blaai na bo'],
      'go to top': ['gaan na bo'],
      'scroll to bottom': ['blaai na onder'],
      'go to bottom': ['gaan na onder'],
      'advanced search': ['gevorderde soektog'],
      'clear search': ['maak soektog skoon'],

      // Metadata & AI
      'read metadata': ['lees metadata'],
      'read all fields': ['lees alle velde'],
      'read record': ['lees rekord'],
      'read all': ['lees alles'],
      'read title': ['lees titel'],
      'what is the title': ['wat is die titel'],
      'read description': ['lees beskrywing'],
      'read the description': ['lees die beskrywing'],
      'describe image': ['beskryf beeld', 'beskryf prent'],
      'describe object': ['beskryf voorwerp'],
      'what is this': ['wat is dit'],
      'what do you see': ['wat sien jy'],
      'what type of file': ['watter tipe lêer'],
      'file type': ['lêertipe'],
      'read text': ['lees teks'],
      'read file': ['lees lêer'],
      'read pdf': ['lees pdf'],
      'read document': ['lees dokument'],

      // Speech control
      'stop reading': ['stop lees', 'hou op lees'],
      'stop speaking': ['stop praat', 'hou op praat'],
      'be quiet': ['wees stil'],
      'silence': ['stilte'],
      'slower': ['stadiger'],
      'speak slower': ['praat stadiger'],
      'faster': ['vinniger'],
      'speak faster': ['praat vinniger'],

      // Listening
      'keep listening': ['hou aan luister', 'bly luister'],
      'continuous listening': ['deurlopende luister'],
      'stop listening': ['stop luister', 'hou op luister'],

      // Help
      'help': ['hulp'],
      'show commands': ['wys opdragte'],
      'what can you do': ['wat kan jy doen'],
      'list commands': ['lys opdragte'],
      'where am i': ['waar is ek'],
      'what page is this': ['watter bladsy is dit'],
      'how many results': ['hoeveel resultate'],
      'how many records': ['hoeveel rekords'],

      // Dictation
      'start dictating': ['begin dikteer'],
      'start dictation': ['begin diktasie'],
      'dictate': ['dikteer'],
      'stop dictating': ['stop dikteer'],
      'stop dictation': ['stop diktasie'],

      // Confirmation
      'yes': ['ja'],
      'no': ['nee'],
      'confirm': ['bevestig'],
      'discard': ['verwerp', 'gooi weg'],
    },

    // ── French ─────────────────────────────────────────────────────────────
    'fr-FR': {
      'go home': ['aller accueil', 'page accueil'],
      'home': ['accueil'],
      'browse': ['parcourir', 'naviguer'],
      'go to admin': ['aller admin', 'administration'],
      'settings': ['paramètres', 'réglages'],
      'clipboard': ['presse-papiers'],
      'go back': ['retour', 'revenir'],
      'back': ['retour'],
      'next page': ['page suivante'],
      'previous page': ['page précédente'],
      'donors': ['donateurs'],
      'research': ['recherche'],
      'authorities': ['autorités'],
      'places': ['lieux'],
      'subjects': ['sujets'],
      'digital objects': ['objets numériques'],
      'browse archive': ['parcourir archives'],
      'browse library': ['parcourir bibliothèque'],
      'browse museum': ['parcourir musée'],
      'browse gallery': ['parcourir galerie'],
      'accessions': ['acquisitions'],
      'repositories': ['dépôts'],
      'institutions': ['institutions'],

      'save': ['enregistrer', 'sauvegarder'],
      'save record': ['enregistrer fiche'],
      'cancel': ['annuler'],
      'delete': ['supprimer', 'effacer'],
      'delete record': ['supprimer fiche'],
      'edit': ['modifier', 'éditer'],
      'edit record': ['modifier fiche'],
      'print': ['imprimer'],

      'first result': ['premier résultat'],
      'sort by title': ['trier par titre'],
      'sort by date': ['trier par date'],

      'scroll down': ['défiler bas'],
      'scroll up': ['défiler haut'],
      'advanced search': ['recherche avancée'],
      'clear search': ['effacer recherche'],

      'read metadata': ['lire métadonnées'],
      'read all': ['lire tout'],
      'read title': ['lire titre'],
      'read description': ['lire description'],
      'describe image': ['décrire image'],
      'describe object': ['décrire objet'],
      'what is this': ['qu\'est-ce que c\'est'],
      'read text': ['lire texte'],
      'read pdf': ['lire pdf'],
      'read document': ['lire document'],

      'stop reading': ['arrêter lecture'],
      'stop speaking': ['arrêter parler'],
      'be quiet': ['tais-toi', 'silence'],
      'slower': ['plus lent'],
      'faster': ['plus vite', 'plus rapide'],

      'keep listening': ['écoute continue'],
      'stop listening': ['arrêter écouter'],

      'help': ['aide'],
      'list commands': ['lister commandes'],
      'where am i': ['où suis-je'],
      'how many results': ['combien de résultats'],

      'start dictating': ['commencer dictée'],
      'stop dictating': ['arrêter dictée'],
      'dictate': ['dicter'],

      'yes': ['oui'],
      'no': ['non'],
      'discard': ['rejeter'],
    },

    // ── Spanish ────────────────────────────────────────────────────────────
    'es-ES': {
      'go home': ['ir inicio', 'página inicio'],
      'home': ['inicio'],
      'browse': ['explorar', 'navegar'],
      'settings': ['configuración', 'ajustes'],
      'go back': ['volver', 'atrás'],
      'back': ['atrás'],
      'next page': ['página siguiente'],
      'previous page': ['página anterior'],
      'donors': ['donantes'],
      'research': ['investigación'],
      'authorities': ['autoridades'],
      'places': ['lugares'],
      'subjects': ['temas'],
      'digital objects': ['objetos digitales'],

      'save': ['guardar'],
      'save record': ['guardar registro'],
      'cancel': ['cancelar'],
      'delete': ['eliminar', 'borrar'],
      'edit': ['editar', 'modificar'],
      'print': ['imprimir'],

      'first result': ['primer resultado'],
      'sort by title': ['ordenar por título'],
      'sort by date': ['ordenar por fecha'],

      'scroll down': ['desplazar abajo'],
      'scroll up': ['desplazar arriba'],
      'advanced search': ['búsqueda avanzada'],

      'read metadata': ['leer metadatos'],
      'read all': ['leer todo'],
      'read title': ['leer título'],
      'read description': ['leer descripción'],
      'describe image': ['describir imagen'],
      'what is this': ['qué es esto'],
      'read text': ['leer texto'],
      'read document': ['leer documento'],

      'stop reading': ['parar lectura'],
      'stop speaking': ['dejar de hablar'],
      'slower': ['más lento'],
      'faster': ['más rápido'],

      'keep listening': ['seguir escuchando'],
      'stop listening': ['dejar de escuchar'],

      'help': ['ayuda'],
      'where am i': ['dónde estoy'],
      'how many results': ['cuántos resultados'],

      'start dictating': ['empezar dictado'],
      'stop dictating': ['parar dictado'],
      'dictate': ['dictar'],

      'yes': ['sí'],
      'no': ['no'],
      'discard': ['descartar'],
    },

    // ── German ─────────────────────────────────────────────────────────────
    'de-DE': {
      'go home': ['zur startseite', 'startseite'],
      'home': ['startseite'],
      'browse': ['durchsuchen', 'blättern'],
      'settings': ['einstellungen'],
      'go back': ['zurück'],
      'back': ['zurück'],
      'next page': ['nächste seite'],
      'previous page': ['vorherige seite'],
      'donors': ['spender'],
      'research': ['forschung'],
      'authorities': ['normdaten'],
      'places': ['orte'],
      'subjects': ['themen'],
      'digital objects': ['digitale objekte'],

      'save': ['speichern'],
      'save record': ['datensatz speichern'],
      'cancel': ['abbrechen'],
      'delete': ['löschen', 'entfernen'],
      'edit': ['bearbeiten'],
      'print': ['drucken'],

      'first result': ['erstes ergebnis'],
      'sort by title': ['nach titel sortieren'],
      'sort by date': ['nach datum sortieren'],

      'scroll down': ['nach unten'],
      'scroll up': ['nach oben'],
      'advanced search': ['erweiterte suche'],

      'read metadata': ['metadaten lesen'],
      'read all': ['alles lesen'],
      'read title': ['titel lesen'],
      'read description': ['beschreibung lesen'],
      'describe image': ['bild beschreiben'],
      'what is this': ['was ist das'],
      'read text': ['text lesen'],
      'read document': ['dokument lesen'],

      'stop reading': ['aufhören zu lesen'],
      'stop speaking': ['aufhören zu sprechen'],
      'be quiet': ['ruhe', 'still'],
      'slower': ['langsamer'],
      'faster': ['schneller'],

      'keep listening': ['weiter zuhören'],
      'stop listening': ['aufhören zuzuhören'],

      'help': ['hilfe'],
      'where am i': ['wo bin ich'],
      'how many results': ['wie viele ergebnisse'],

      'start dictating': ['diktat starten'],
      'stop dictating': ['diktat stoppen'],
      'dictate': ['diktieren'],

      'yes': ['ja'],
      'no': ['nein'],
      'discard': ['verwerfen'],
    },

    // ── Portuguese ─────────────────────────────────────────────────────────
    'pt-PT': {
      'go home': ['ir para início', 'página inicial'],
      'home': ['início'],
      'browse': ['explorar', 'navegar'],
      'settings': ['configurações', 'definições'],
      'go back': ['voltar'],
      'back': ['voltar'],
      'next page': ['próxima página'],
      'previous page': ['página anterior'],

      'save': ['guardar', 'salvar'],
      'cancel': ['cancelar'],
      'delete': ['eliminar', 'apagar'],
      'edit': ['editar'],
      'print': ['imprimir'],

      'read all': ['ler tudo'],
      'read title': ['ler título'],
      'read description': ['ler descrição'],
      'describe image': ['descrever imagem'],
      'what is this': ['o que é isto'],

      'stop reading': ['parar leitura'],
      'slower': ['mais lento'],
      'faster': ['mais rápido'],

      'help': ['ajuda'],
      'where am i': ['onde estou'],

      'yes': ['sim'],
      'no': ['não'],
      'discard': ['descartar'],
    },

    // ── isiZulu ────────────────────────────────────────────────────────────
    'zu-ZA': {
      'go home': ['hamba ekhaya'],
      'home': ['ekhaya', 'ikhaya'],
      'go back': ['buyela emuva'],
      'back': ['emuva'],
      'next page': ['ikhasi elilandelayo'],
      'previous page': ['ikhasi elidlule'],
      'save': ['gcina', 'londoloza'],
      'cancel': ['khansela'],
      'delete': ['susa'],
      'edit': ['hlela'],
      'help': ['usizo'],
      'yes': ['yebo'],
      'no': ['cha'],
      'search': ['sesha'],
      'read all': ['funda konke'],
      'stop reading': ['yeka ukufunda'],
      'where am i': ['ngikuphi'],
    },

    // ── isiXhosa ───────────────────────────────────────────────────────────
    'xh-ZA': {
      'go home': ['yiya ekhaya'],
      'home': ['ekhaya', 'ikhaya'],
      'go back': ['buyela emva'],
      'back': ['emva'],
      'save': ['gcina', 'londoloza'],
      'cancel': ['rhoxisa'],
      'delete': ['cima'],
      'edit': ['hlela'],
      'help': ['uncedo'],
      'yes': ['ewe'],
      'no': ['hayi'],
      'search': ['khangela'],
      'read all': ['funda konke'],
      'stop reading': ['yeka ukufunda'],
      'where am i': ['ndiphi'],
    },

    // ── Sesotho ────────────────────────────────────────────────────────────
    'st-ZA': {
      'go home': ['eya hae'],
      'home': ['hae', 'lehae'],
      'go back': ['kgutla morao'],
      'back': ['morao'],
      'save': ['boloka'],
      'cancel': ['hlakola'],
      'delete': ['hlakola'],
      'edit': ['fetola'],
      'help': ['thuso'],
      'yes': ['ee'],
      'no': ['tjhe'],
      'search': ['batla'],
      'read all': ['bala tsohle'],
      'where am i': ['ke hokae'],
    },
  };

  return {
    /**
     * Get translated patterns for a language.
     * Returns a flat map: { 'english pattern' => ['translated1', 'translated2'] }
     */
    getForLanguage: function (langCode) {
      // Try exact match first (e.g., 'af-ZA'), then base language (e.g., 'af')
      return translations[langCode] || translations[langCode.split('-')[0]] || {};
    },

    /**
     * Merge translated patterns into a command's patterns array.
     * Returns a new patterns array with originals + translations appended.
     */
    mergePatterns: function (originalPatterns, langCode) {
      if (!langCode || langCode === 'en-US' || langCode === 'en-GB') {
        return originalPatterns; // English — no translations needed
      }

      var trans = this.getForLanguage(langCode);
      if (!trans || Object.keys(trans).length === 0) {
        return originalPatterns;
      }

      var merged = originalPatterns.slice(); // Copy original
      for (var i = 0; i < originalPatterns.length; i++) {
        var p = originalPatterns[i];
        // Only translate string patterns (not regex)
        if (typeof p === 'string' && trans[p]) {
          for (var j = 0; j < trans[p].length; j++) {
            if (merged.indexOf(trans[p][j]) === -1) {
              merged.push(trans[p][j]);
            }
          }
        }
      }
      return merged;
    },

    /**
     * Get all available language codes.
     */
    getLanguages: function () {
      return Object.keys(translations);
    }
  };
})();
