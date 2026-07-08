% =============================================================================
% MOTEUR DE PRISE DE DÉCISION HOSPITALIÈRE ENRICHI - SEADO
% =============================================================================

:- use_module(library(http/thread_httpd)).
:- use_module(library(http/http_dispatch)).
:- use_module(library(http/http_parameters)).
:- use_module(library(http/html_write)).
:- use_module(library(http/http_client)). 

:- dynamic file_attente/2.
:- dynamic patient/3.

% Services hospitaliers disponibles.
service(urgence).       
service(cardiologie).
service(orthopedie).
service(gyneco).
service(imagerie).
service(pediatrie).
service(neurologie).
service(autre).

% Initialisation des files d'attente.
init_files_attente :-
    retractall(file_attente(_, _)),
    forall(service(Service), assertz(file_attente(Service, []))).

% Remise à zéro des patients.
init_patients :-
    retractall(patient(_, _, _)).

% Normalisation : transforme la chaîne en une vraie liste de sous-chaînes épurées.
normalize_string(Input, MotsNettoyes) :-
    string_lower(Input, Lower),
    split_string(Lower, " ", " ,.!?;:-()\"'", MotsNettoyes).

% =============================================================================
% SYSTÈME DE DÉCISION INTELLIGENT AVEC SECU-FILTRAGE
% =============================================================================

% Évaluation principale appelée par le script
evaluer_requete(MotsPatient, Resultat) :-
    % Règle 1 : Intercepter les salutations simples en premier
    ( contient_mot_cle(MotsPatient, ["salut", "bonjour", "bonsoir", "hey", "coucou", "allo", "wesh"]) -> 
        Resultat = salutation
    ; 
    % Règle 2 : Forcer une explication si l'utilisateur met moins de 3 mots
    length(MotsPatient, Longueur), Longueur < 3 -> 
        Resultat = trop_court
    ;
    % Règle 3 : Analyser les correspondances médicales
    analyser_services(MotsPatient, Statut) -> 
        Resultat = Statut
    ;
    % Par défaut : Hors-contexte médical
        Resultat = reception
    ).

% Analyse détaillée des symptômes
analyser_services(MotsPatient, ok(urgence)) :-
    contient_mot_cle(MotsPatient, ["urgence", "accident", "saignement", "perte", "coma", "inconscient", "choc", "hemorragie", "blesse"]), !.

analyser_services(MotsPatient, ok(cardiologie)) :-
    contient_mot_cle(MotsPatient, ["coeur", "poitrine", "arythmie", "palpitation", "essoufflement", "tension", "hypertension", "thoracique"]), !.

analyser_services(MotsPatient, ok(orthopedie)) :-
    contient_mot_cle(MotsPatient, ["os", "articulation", "fracture", "genou", "coude", "tendinite", "blessure", "entorse", "pied", "main", "dos", "jambe"]), !.

analyser_services(MotsPatient, ok(gyneco)) :-
    contient_mot_cle(MotsPatient, ["gynecologie", "regles", "grossesse", "pelvis", "col", "sein", "vagin", "infertilite", "enceinte", "accouchement"]), !.

analyser_services(MotsPatient, ok(imagerie)) :-
    contient_mot_cle(MotsPatient, ["radio", "scanner", "irm", "echographie", "imagerie", "radiographie", "x-ray"]), !.

analyser_services(MotsPatient, ok(pediatrie)) :-
    contient_mot_cle(MotsPatient, ["enfant", "bebe", "pediatrie", "vaccin", "nourrisson", "fiston", "fillette"]), !.

analyser_services(MotsPatient, ok(neurologie)) :-
    contient_mot_cle(MotsPatient, ["migraine", "paralysie", "convulsion", "crise", "memoire", "nerf", "cerveau", "vertige", "tete"]), !.

analyser_services(MotsPatient, ok(autre)) :-
    contient_mot_cle(MotsPatient, ["fatigue", "malaise", "douleur", "symptome", "fievre", "grippe", "toux", "malade", "vomir"]), !.

% Prédicat utilitaire d'intersection
contient_mot_cle(MotsPatient, ListeMotsCles) :-
    member(Mot, MotsPatient),
    member(Mot, ListeMotsCles),
    !.

% =============================================================================
% GESTION DE LA FILE D'ATTENTE
% =============================================================================

ajouter_patient(Service, Description, PatientId) :-
    gensym(patient_, PatientId),
    assertz(patient(PatientId, Service, Description)),
    ( retract(file_attente(Service, Liste)) ->
        append(Liste, [PatientId], NouvelleListe),
        assertz(file_attente(Service, NouvelleListe))
    ; assertz(file_attente(Service, [PatientId]))
    ).

% =============================================================================
% SERVEUR WEB EMBARQUÉ PROLOG (Pour exécution directe sans PHP)
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
        title('Prise de decision hospitaliere - SEADO'),
        [
            style('body { font-family: Arial; margin: 40px; background: #f4f6f9; } .box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); max-width: 600px; } button { background: #3498db; color: white; border:none; padding: 10px 20px; border-radius: 4px; cursor:pointer; } input[type=text] { width: 80%; padding: 10px; margin-bottom: 15px; }'),
            div([class=box], [
                h1('Accueil Orientation Connectée'),
                p('Expliquez brièvement votre situation ou vos symptômes :'),
                form([action='/submit', method='POST'],
                     [
                         input([type(text), name(complaint), placeholder('Ex: J\'ai des douleurs au coeur...'), required(true)], []),
                         br([]),
                         button([type(submit)], 'Valider mon orientation')
                     ])
            ])
        ]
    ).

recevoir_formulaire(Request) :-
    http_read_data(Request, Data, []),
    member(complaint=Complaint, Data),
    normalize_string(Complaint, MotsCles),
    
    evaluer_requete(MotsCles, Resultat),
    
    ( Resultat = salutation ->
        Titre = 'IA : Message incomplet', Couleur = '#f39c12',
        Contenu = [h2('Bonjour !'), p('Vous n\'avez saisi qu\'une salutation. Veuillez écrire une phrase décrivant vos symptômes.')]
    ; Resultat = trop_court ->
        Titre = 'IA : Message trop court', Couleur = '#9b59b6',
        Contenu = [h2('Précision demandée'), p('Votre message est trop court. Veuillez donner une brève explication (ex: "J\'ai mal à la tête depuis ce matin") pour permettre un routage précis.')]
    ; Resultat = reception ->
        Titre = 'IA : Demande hors contexte', Couleur = '#e74c3c',
        Contenu = [h2('Orientation automatique impossible'), p('Cette demande ne correspond à aucune file active. Veuillez consulter le réceptionniste à l\'accueil général.')]
    ; Resultat = ok(Service) ->
        ajouter_patient(Service, Complaint, PatientId),
        Titre = 'IA : Succès', Couleur = '#2ecc71',
        Contenu = [h2('Ticket généré !'), p([b('Service : '), Service]), p([b('Numéro d\'attente : '), PatientId])]
    ),
    
    reply_html_page(title(Titre), [
        style(format(string('body { font-family: Arial; margin: 40px; } .card { border-left: 8px solid ~w; background: #fff; padding: 25px; border-radius: 5px; box-shadow:0 2px 10px rgba(0,0,0,0.05); max-width: 650px; }'), [Couleur])),
        div([class=card], [Contenu, br([]), a([href('/')], 'Retourner à l\'accueil')])
    ]).