Dazzlink VIN Recall Manager Standalone 3.0.2
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
- API base URL: ολόκληρο το base με όποιο prefix έδωσαν,
  π.χ. https://HOST/api/externalIntegration (χωρίς τελικό /v1/eu/...)
  Το plugin προσθέτει /v1/eu/dms/getUnfinishedTechnologyActivityByVin.
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
- Μία ανανέωση token σε HTTP 401/403 και επανάληψη lookup.
- Μία σύντομη επανάληψη για transport errors ή HTTP 502/503/504.
- Timeout 12 δευτ. ανά κλήση, redirects απενεργοποιημένα, έλεγχος TLS ενεργός.
- Δεν επαναλαμβάνονται HTTP 429 ή άγνωστα business errors.
- vinExist=true και count>0: εκκρεμείς τεχνικές ενέργειες.
- vinExist=true και count=0: δεν υπάρχουν εκκρεμείς ενέργειες.
- vinExist=false: το VIN δεν βρέθηκε (δεν σημαίνει ότι δεν έχει ανακλήσεις).
- vinExist=null/απουσία, λάθος VIN, ελλιπές payload ή error: αδυναμία ελέγχου.
- Δεν αποθηκεύονται αποτελέσματα VIN και δεν χρησιμοποιείται CSV fallback.
- Δεν επιστρέφονται ούτε επινοούνται campaign/action code ή description.
- Τα κείμενα υποστηρίζουν {vin}, {count}, {minutes}, {contact}, ως απλό κείμενο.
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
