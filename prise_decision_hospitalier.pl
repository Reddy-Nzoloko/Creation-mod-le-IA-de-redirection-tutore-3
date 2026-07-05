% =============================================================================
% MOTEUR DE PRISE DE DÉCISION HOSPITALIÈRE - SEADO
% =============================================================================

:- use_module(library(http/thread_httpd)).
:- use_module(library(http/http_dispatch)).
:- use_module(library(http/http_parameters)).
:- use_module(library(http/html_write)).

:- dynamic file_attente/2.
:- dynamic patient/3.

% Services hospitaliers disponibles.
service(urgence).       % Placé en premier pour priorité d'évaluation
service(cardiologie).
service(orthopedie).
service(gyneco).
service(imagerie).
service(pediatrie).
service(neurologie).
service(autre).

% Initialisation des files d'attente (Correction de la parenthèse manquante).
init_files_attente :-
    retractall(file_attente(_, _)),
    forall(service(Service), assertz(file_attente(Service, []))).

% Remise à zéro des patients.
init_patients :-
    retractall(patient(_, _, _)).

% Démarrage du système en mode console.
start :-
    init_files_attente,
    init_patients,
    write('=== Prise de decision hospitaliere ==='), nl,
    write('Entrez votre plainte textuelle.'), nl,
    write('Tapez "stop." pour arreter.'), nl,
    loop.

% Boucle principale de la console.
loop :-
    write('\nVotre plainte : '),
    read_line_to_string(user_input, Ligne),
    ( Ligne = "stop." ->
        write('Fin du service.'), nl
    ; process_input(Ligne),
      loop
    ).

% Traitement de la plainte.
process_input(Ligne) :-
    normalize_string(Ligne, MotsCles),
    ( service_recommande(MotsCles, Service) ->
        format('Service recommande : ~w~n', [Service]),
        ajouter_patient(Service, Ligne, PatientId),
        format('Patient ajoute avec ID ~w dans la file de ~w.~n', [PatientId, Service]),
        afficher_file(Service)
    ; % Si rien ne match, on oriente d'office vers la Médecine Générale (autre)
        format('Service par defaut recommande : autre~n'),
        ajouter_patient(autre, Ligne, PatientId),
        format('Patient ajoute avec ID ~w dans la file de autre.~n', [PatientId]),
        afficher_file(autre)
    ).

% Normalisation : transforme la chaîne en une vraie liste de sous-chaînes épurées.
normalize_string(Input, MotsNettoyes) :-
    string_lower(Input, Lower),
    split_string(Lower, " ", " ,.!?;:-()\"'", MotsNettoyes).

% =============================================================================
% RÈGLES DE RECOMMANDATION DE SERVICE (Basées sur l'intersection de listes)
% =============================================================================
% On vérifie si au moins un des mots du patient appartient à la liste du service.

service_recommande(MotsPatient, urgence) :-
    contient_mot_cle(MotsPatient, ["urgence", "accident", "saignement", "perte", "coma", "inconscient", "choc", "hemorragie"]).

service_recommande(MotsPatient, cardiologie) :-
    contient_mot_cle(MotsPatient, ["coeur", "poitrine", "arythmie", "palpitation", "essoufflement", "tension", "hypertension", "thoracique"]).

service_recommande(MotsPatient, orthopedie) :-
    contient_mot_cle(MotsPatient, ["os", "articulation", "fracture", "genou", "coude", "tendinite", "blessure", "entorse", "pied", "main", "dos"]).

service_recommande(MotsPatient, gyneco) :-
    contient_mot_cle(MotsPatient, ["gynecologie", "regles", "grossesse", "pelvis", "col", "sein", "vagin", "infertilite", "enceinte", "accouchement"]).

service_recommande(MotsPatient, imagerie) :-
    contient_mot_cle(MotsPatient, ["radio", "scanner", "irm", "echographie", "imagerie", "radiographie", "x-ray"]).

service_recommande(MotsPatient, pediatrie) :-
    contient_mot_cle(MotsPatient, ["enfant", "bebe", "pediatrie", "vaccin", "nourrisson", "fiston", "fillette"]).

service_recommande(MotsPatient, neurologie) :-
    contient_mot_cle(MotsPatient, ["migraine", "paralysie", "convulsion", "crise", "memoire", "nerf", "cerveau", "vertige"]).

service_recommande(MotsPatient, autre) :-
    contient_mot_cle(MotsPatient, ["fatigue", "malaise", "douleur", "symptome", "fievre", "grippe", "toux"]).

% Prédicat utilitaire : Réussit si un élément de la liste 1 est présent dans la liste 2
contient_mot_cle(MotsPatient, ListeMotsCles) :-
    member(Mot, MotsPatient),
    member(Mot, ListeMotsCles),
    !. % Le '!' évite de chercher d'autres correspondances si une est trouvée.

% =============================================================================
% GESTION DE LA FILE D'ATTENTE
% =============================================================================

% Ajout du patient dans la file d'attente (FIFO).
ajouter_patient(Service, Description, PatientId) :-
    gensym(patient_, PatientId),
    assertz(patient(PatientId, Service, Description)),
    ( retract(file_attente(Service, Liste)) ->
        append(Liste, [PatientId], NouvelleListe),
        assertz(file_attente(Service, NouvelleListe))
    ; % Sécurité si la file n'était pas initialisée
        assertz(file_attente(Service, [PatientId]))
    ).

% Affichage de la file d'attente d'un service.
afficher_file(Service) :-
    file_attente(Service, Liste),
    format('File de ~w : ~w~n', [Service, Liste]).

% Afficher toutes les files.
afficher_toutes_files :-
    nl, write('--- ETAT ACTUEL DES FILES ---'), nl,
    forall(file_attente(Service, Liste),
           format('~w : ~w~n', [Service, Liste])).

% Scénario de test automatique
example :-
    init_files_attente,
    init_patients,
    process_input("J'ai une forte douleur thoracique et un essoufflement"),
    process_input("Mon enfant a une forte temperature"),
    process_input("J'ai fait une chute et je suspecte une fracture du genou"),
    process_input("Je viens pour une echographie de controle"),
    afficher_toutes_files.

% =============================================================================
% SERVEUR WEB MINIMAL POUR LE FORMULAIRE
% =============================================================================

:- http_handler(root(.), accueil_page, []).
:- http_handler(root(submit), recevoir_formulaire, []).

server(Port) :-
    init_files_attente,
    init_patients,
    format('Demarrage du serveur sur le port ~w~n', [Port]),
    http_server(http_dispatch, [port(Port)]).

accueil_page(_Request) :-
    reply_html_page(
        title('Prise de decision hospitaliere'),
        [
            h1('Prise de decision hospitaliere'),
            p('Décrivez votre problème ci-dessous. Le système Prolog va recommander un service et ajouter le patient à la file d''attente.'),
            form([action='/submit', method='POST'],
                 [
                     label([for(complaint)], 'Que se passe-t-il ?'),
                     br([]),
                     textarea([id(complaint), name(complaint), rows(5), cols(50), placeholder('Par exemple : j''ai une douleur thoracique et un essoufflement...')], []),
                     br([]),
                     button([type(submit)], 'Envoyer')
                 ]),
            h2('Exemples de phrases :'),
            ul([
                li('J''ai une douleur au coeur et je suis essoufflé.'),
                li('Mon enfant a de la fièvre et des vomissements.'),
                li('Je veux une échographie de contrôle.'),
                li('J''ai subi un accident et j''ai mal à la jambe.')
            ])
        ]
    ).

recevoir_formulaire(Request) :-
    http_read_data(Request, Data, []),
    member(complaint=Complaint, Data),
    normalize_string(Complaint, MotsCles),
    ( service_recommande(MotsCles, Service) ->
        ajouter_patient(Service, Complaint, PatientId),
        format(string(Message), 'Service recommandé : ~w. Patient ajouté avec ID ~w.', [Service, PatientId])
    ;
        Service = autre,
        ajouter_patient(autre, Complaint, PatientId),
        format(string(Message), 'Service recommandé : autre. Patient ajouté avec ID ~w.', [PatientId])
    ),
    reply_html_page(
        title('Resultat de la requete'),
        [
            h1('Résultat'),
            p(Message),
            p(['Description : ', Complaint]),
            p(['Service : ', Service]),
            p(['ID du patient : ', PatientId]),
            a([href('/')], 'Retour au formulaire')
        ]
    ).