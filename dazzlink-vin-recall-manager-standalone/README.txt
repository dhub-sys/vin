Dazzlink VIN Recall Manager Standalone 3.1.0
===========================================
API-only IAS/iDMS integration. No CSV, no Contact Form 7.
Shortcode: [vin_recall_search]
Requirements: WordPress 6.2+, PHP 7.4+, OpenSSL, outbound HTTPS access to factory.

ΕΓΚΑΤΑΣΤΑΣΗ / ΑΝΑΒΑΘΜΙΣΗ
1. Κρατήστε backup και δοκιμάστε πρώτα σε staging/UAT.
2. Απενεργοποιήστε το προηγούμενο VIN Recall plugin (και τυχόν CF7 edition).
3. Ανεβάστε το ZIP: Πρόσθετα > Προσθήκη νέου > Μεταφόρτωση προσθέτου.
   Για το standalone 2.1.0 επιλέξτε αντικατάσταση με τη νέα έκδοση.
4. Ενεργοποιήστε τη νέα έκδοση. Μόνο μία έκδοση VIN Recall πρέπει να είναι ενεργή.
5. VIN Recall > API & Messages: συμπληρώστε τα στοιχεία που έδωσε το εργοστάσιο.
6. VIN Recall > Diagnostics: Test Connection και στη συνέχεια Test VIN lookup.
7. Τοποθετήστε [vin_recall_search] σε σελίδα. Αν υπάρχει ήδη, παραμένει ίδιο.
   Αν η παλιά σελίδα περιέχει shortcode CF7, αντικαταστήστε το με το παραπάνω.
8. Ελέγξτε στη δημόσια σελίδα VIN με ενέργειες, χωρίς ενέργειες, άγνωστο VIN,
   μη έγκυρο VIN και αδυναμία API. Έπειτα περάστε στα production στοιχεία.

API SETTINGS
- Auth / Token endpoint: ολόκληρο το URL που έστειλε το εργοστάσιο,
  συνήθως https://HOST/api/auth/getToken. Δεν προστίθεται path αυτόματα.
- API base URL: όπως το έδωσε το εργοστάσιο. Γίνονται δεκτές και οι τρεις μορφές:
  https://HOST                         → προστίθεται /api/externalIntegration/v1/eu/dms/...
  https://HOST/api/externalIntegration → προστίθεται /v1/eu/dms/...
  ολόκληρο το URL .../getUnfinishedTechnologyActivityByVin → χρησιμοποιείται ως έχει.
  Το τελικό URL εμφανίζεται κάτω από το πεδίο στις ρυθμίσεις.

UAT (Chery Greece, από το email του εργοστασίου)
- Auth / Token endpoint: https://ipaas-admin-om-sit.omodaglobal.com/api/auth/getToken
- API base URL:          https://ipaas-om-sit.omodaglobal.com
- Αποστολή App Secret:   «Όπως δόθηκε», αφού το curl του εργοστασίου στέλνει το
  app-secret αυτούσιο. Αν το Test Connection αποτύχει, δοκιμάστε «Raw → AES».
- IP whitelisting: δεν απαιτείται στο UAT.
- Test VINs (αναμενόμενα): LVTDB21B1SD231930 → 1 ενέργεια, 12 λεπτά
                           LVTDB21B7ADC42523 → 1 ενέργεια, 6 λεπτά
                           LVTDB21B7ADC42522 → 1 ενέργεια, 6 λεπτά
- App ID / App Secret: συμπληρώνονται μόνο στο WordPress admin.
- Environment: ετικέτα UAT/production. Δεν επιλέγει αυτόματα endpoints και
  δεν διατηρεί ξεχωριστά προφίλ. Αλλάξτε όλα τα στοιχεία όταν αλλάζετε περιβάλλον.
- Κενό πεδίο secret/key/IV στην αποθήκευση διατηρεί την υπάρχουσα τιμή.

APP SECRET / AES
Προεπιλογή: raw App Secret → AES-128-CBC με PKCS padding → lowercase HEX.
Key και IV: CheryOdin!202503, όπως στο εκτελέσιμο παράδειγμα Java του PDF.
Είναι και τα δύο 16 bytes. Κενά AES key/IV χρησιμοποιούν αυτές τις προεπιλογές.
Αν το εργοστάσιο δώσει διαφορετικά στοιχεία, συμπληρώστε τα ως ακριβές κείμενο
UTF-8 (όχι HEX/base64). Έλεγχος key 16/24/32 bytes και IV 16 bytes.
Αν το εργοστάσιο έδωσε ήδη encrypted app-secret HEX, επιλέξτε encrypted mode.
Τότε key/IV αγνοούνται και η τιμή δεν κρυπτογραφείται δεύτερη φορά.
Η περιγραφή του PDF αναφέρει αλλού συντομότερο key· ακολουθούμε το πραγματικό
παράδειγμα Java. Το Test Connection θα επιβεβαιώσει τις απαιτήσεις του server.

ΣΥΜΠΕΡΙΦΟΡΑ
- GET token με headers app-id, app-secret (encrypted HEX), σύμφωνα με το curl του εργοστασίου.
- Cache token 4 ώρες 55 λεπτά (5 λεπτά πριν το documented TTL 5 ωρών).
- Lookup POST JSON {"vin":"..."}, headers APP-ID / APP-TOKEN.
- Μία ανανέωση token σε HTTP 401/403 ή σε HTTP 200 με code≠0 χωρίς data object
  (απόρριψη token από το gateway), και επανάληψη lookup.
- Μία σύντομη επανάληψη για transport errors ή HTTP 502/503/504.
- Timeout 8 δευτ. ανά κλήση και συνολικό όριο 25 δευτ. ανά έλεγχο (token, ανανέωση,
  επαναλήψεις), κάτω από το συνηθισμένο max_execution_time 30 δευτ. της PHP.
  Redirects απενεργοποιημένα, έλεγχος TLS ενεργός.
- Δεν επαναλαμβάνονται HTTP 429 ή άγνωστα business errors.
- vinExist=true και count>0: εκκρεμείς τεχνικές ενέργειες.
- vinExist=true και count=0: δεν υπάρχουν εκκρεμείς ενέργειες.
- vinExist=false: το VIN δεν βρέθηκε (δεν σημαίνει ότι δεν έχει ανακλήσεις).
- vinExist=null/απουσία, λάθος VIN, ελλιπές payload ή error: αδυναμία ελέγχου.
- totalLaborMinutes null/απουσία: το αποτέλεσμα εμφανίζεται κανονικά, {minutes}
  και {duration} μένουν κενά. unfinishedCount δεκτό και ως ακέραιο string.
- Κενά, παύλες και τελείες στο VIN αφαιρούνται (π.χ. επικόλληση από έγγραφο).
- Δεν αποθηκεύονται αποτελέσματα VIN και δεν χρησιμοποιείται CSV fallback.
- Δεν επιστρέφονται ούτε επινοούνται campaign/action code ή description.
- Τα κείμενα υποστηρίζουν {vin}, {count}, {minutes}, {duration}, {contact}, ως απλό κείμενο.
- Προαιρετικός σύνδεσμος ραντεβού και τηλέφωνο service: εμφανίζονται ως κουμπί και
  σύνδεσμος κλήσης όταν υπάρχουν ενέργειες ή το VIN δεν βρέθηκε.
- Το Test Connection ελέγχει authentication. Το Test VIN ελέγχει iDMS lookup.

ΠΡΟΣΤΑΣΙΑ / ΛΕΙΤΟΥΡΓΙΑ
Τα secrets αποθηκεύονται κρυπτογραφημένα με AES-256-GCM, κλειδί που προκύπτει
από τα WordPress auth salts. Αλλαγή salts απαιτεί επανεισαγωγή secrets.
Το token αποθηκεύεται σε WordPress transient και δεν εμφανίζεται στο UI.
Μόνο διαχειριστές με manage_options αλλάζουν ρυθμίσεις ή εκτελούν diagnostics.
Δημόσια φόρμα: server-side VIN validation, φρέσκο nonce για cached pages,
best-effort όρια 10 requests/λεπτό ανά peer IP και 100/λεπτό συνολικά.
Αυτά τα όρια δεν είναι atomic ούτε υποκαθιστούν το WAF/rate limiting του host.
Πίσω από reverse proxy μπορεί πολλοί επισκέπτες να έχουν την ίδια peer IP.
Τα logs περιέχουν μόνο χρόνο, operation και status code, έως 30 εγγραφές.
Δεν καταγράφονται VIN, IP, secret, token ή raw responses από το plugin.
Το nonce δεν είναι authentication για τον δημόσιο έλεγχο.
Το GET auth στέλνει encrypted secret στα headers: βεβαιωθείτε
ότι τυχόν server/proxy/APM logs αποκρύπτουν app-secret και APP-TOKEN.
Η παλιά CSV διαχείριση έχει αφαιρεθεί. Παλιό CSV και παλιές ρυθμίσεις δεν
διαγράφονται αυτόματα και δεν διαβάζονται για αναζητήσεις. Αν χρειάζεται
διαγραφή παλιών αρχείων, διαχειριστείτε τα ξεχωριστά μετά από backup.
Ασφαλή κείμενα εμφάνισης της 2.1.0 μεταφέρονται κατά την πρώτη ενεργοποίηση.
Τα παλιά result messages αντικαθίστανται επειδή προϋπέθεταν κωδικό/περιγραφή.

VALIDATION SCOPE
Mock tests and syntax checks are development checks, not factory certification.
No real credentials are bundled. UAT and production validation must be performed
on the target WordPress host with known test VINs and confirmed encryption.

CHANGELOG 3.1.0
- Ξεχωριστό μήνυμα για 1 εκκρεμή ενέργεια («υπάρχει 1 εκκρεμής τεχνική ενέργεια»).
- Το API base URL γίνεται δεκτό και ως σκέτο host (όπως το δίνει το εργοστάσιο):
  προστίθεται αυτόματα το /api/externalIntegration.
- Ανανέωση token και όταν το gateway την απορρίπτει με HTTP 200 + code≠0.
  Πριν, η φόρμα έβγαζε σφάλμα μέχρι να λήξει το cached token (έως 5 ώρες).
- Συνολικό χρονικό όριο 25 δευτ. ανά έλεγχο: πριν, ο χειρότερος συνδυασμός
  επαναλήψεων μπορούσε να ξεπεράσει τα 90 δευτ. και να κοπεί από την PHP.
- Δεκτό null totalLaborMinutes και unfinishedCount ως string, και VIN στην απάντηση
  με πεζά ή κενά. Οι υπόλοιποι αυστηροί έλεγχοι παραμένουν.
- Αφαίρεση κενών, παυλών και τελειών από το VIN (server και browser). Το πεδίο δεν
  κόβει πλέον επικολλημένο VIN με κενά.
- Το script ξεκινά και όταν φορτώνεται καθυστερημένα από plugins βελτιστοποίησης.
- Αφαίρεση κενών αρχής/τέλους από App ID, App Secret, AES και URLs κατά την αποθήκευση.
- Ελληνικές ετικέτες και επεξηγήσεις στις ρυθμίσεις, εμφάνιση του τελικού URL ερωτήματος.
- Προειδοποίηση όταν τα αποθηκευμένα secrets δεν αποκρυπτογραφούνται (αλλαγή salts),
  και ένδειξη UAT. Επαναφορά AES key/IV στις προεπιλογές.
- Νέα μεταβλητή {duration} (π.χ. «1 ώρα και 35 λεπτά»), σύνδεσμος ραντεβού και
  τηλέφωνο, ξεχωριστό στυλ για «δεν βρέθηκε».
- Το Test VIN δείχνει και το μήνυμα που θα δει ο επισκέπτης.
- uninstall.php: η διαγραφή του plugin αφαιρεί ρυθμίσεις, logs, token και μετρητές.

CHANGELOG 3.0.2
Διόρθωση token request: app-id και app-secret αποστέλλονται στα headers,
σύμφωνα με το πραγματικό UAT curl που έδωσε το εργοστάσιο.
Τα αποθηκευμένα credentials και κείμενα διατηρούνται στην αναβάθμιση.

CHANGELOG 3.0.2
Επιλογή «Όπως δόθηκε — χωρίς επιπλέον κρυπτογράφηση»: στέλνει το αποθηκευμένο
App Secret αυτούσιο στο header app-secret, όπως στο παράδειγμα curl.
Για τη δοκιμή: επιλέξτε αυτό το mode, κρατήστε κενό το πεδίο App Secret ώστε
να διατηρηθεί η τιμή, αποθηκεύστε και εκτελέστε Test Connection.
Δεν αλλάζει αυτόματα ο προηγούμενος τρόπος αποστολής στην αναβάθμιση.
Το diagnostic authentication δείχνει μόνο αριθμητικό code και γνωστά ονόματα
πεδίων/τύπους, όχι msg/message text, credentials ή περιεχόμενο token.
