# TicketFilter Mailgate Verwerkingsflow

Dit document beschrijft de volledige flow van een binnenkomende e-mailbatch via GLPI's Mailgate (`MailCollector`) in combinatie met de TicketFilter-plugin.

---

## 1. Sequentiediagram (Levenscyclus van een Batch)

```mermaid
sequenceDiagram
    autonumber
    actor Mailbox as IMAP Server (Office 365)
    participant MC as GLPI MailCollector
    participant Engine as TicketFilter FilterEngine
    participant Processor as TicketProcessor
    participant DB as GLPI Database
    participant Shutdown as Shutdown Handler

    %% Fase 1: Ophalen
    Note over Mailbox, MC: Fase 1: Batch Initialisatie
    MC->>Mailbox: storage->current() & getUniqueId()
    Mailbox-->>MC: Retourneert header-lijst ($messages: UID 1001, UID 1002)

    %% Fase 2: E-mail 1 verwerken
    Note over MC, DB: Fase 2: Verwerking E-mail 1 (UID 1001)
    MC->>MC: buildTicket(UID 1001)
    MC->>MC: addItemFromMessage(Ticket, $tkt)
    MC->>Engine: Hook pre_item_add ($ticket)
    
    rect rgb(240, 245, 255)
        Note over Engine, Processor: TicketFilter Evaluatie
        Engine->>Engine: PatternMatcher: regex match token (bv. CRU-5001)
        Engine->>DB: Zoek tickets met token (laagste ID eerst)
        Engine->>Processor: verifySender(beleidscontrole afzender)
        alt Afzender geautoriseerd
            Processor->>DB: Ticket gesloten? Eventueel heropenen
            Processor->>DB: ITILFollowup::add (originele e-mail body)
            Processor->>DB: ITILFollowup::add (privé auditnotitie, _disablenotif=true)
            Engine->>Engine: recordMailgateInterception: plaats UID 1001 in wachtrij
            Engine-->>MC: $ticket->input = false (annuleer nieuw ticket)
        end
    end

    MC->>MC: addItemFromMessage faalt ($input=false)
    MC->>Engine: Hook pre NotImportedEmail ($rejinput)
    Engine-->>MC: $item->input = false (onderdruk foutrecord in DB)
    MC->>MC: Registreer $delete[1001] = 'refused' (IMAP blijft onaangeraakt!)

    %% Fase 2: E-mail 2 verwerken
    Note over MC, DB: Fase 2 (vervolg): Verwerking E-mail 2 (UID 1002)
    Note over Mailbox, MC: IMAP-sessie is intact: geen veranderde sequence-nummers
    MC->>MC: buildTicket(UID 1002)
    alt Regulier ticket
        MC->>DB: Ticket::add (nieuw ticket aangemaakt)
        MC->>MC: Registreer $delete[1002] = 'accepted'
    else Match met filter
        MC->>Engine: Hook pre_item_add (samengevoegd & geplaatst in wachtrij)
        MC->>MC: Registreer $delete[1002] = 'refused'
    end

    %% Fase 3: Batch afronding
    Note over MC, Mailbox: Fase 3: GLPI Batch Afronding
    MC->>DB: Werk last_collect_date bij
    MC->>MC: Stel sessie-notificatie samen (bevat initieel errors=1)

    %% Fase 4: Shutdown opschoning
    Note over Shutdown, Mailbox: Fase 4: PHP Shutdown (handleMailgateShutdown)
    Shutdown->>Engine: Haal wachtrij met samengevoegde berichten op
    loop Voor elke collector (uids: 1001, ...)
        Shutdown->>Mailbox: Nieuwe dedicated IMAP-verbinding (MailCollector->connect)
        Shutdown->>Mailbox: deleteMails(UID 1001, 'accepted' / INBOX/Verwerkt)
        Shutdown->>Mailbox: storage->close() (stuurt LOGOUT & IMAP EXPUNGE)
        Mailbox-->>Shutdown: E-mail definitief verplaatst/verwijderd uit Inbox
    end
    Shutdown->>DB: Verwijder eventuele restrecords uit glpi_notimportedemails
    Shutdown->>Shutdown: Corrigeer $_SESSION melding: errors=1 -> errors=0 + succesbanner
```

---

## 2. Stroomschema (Beslisboom per E-mail)

```mermaid
flowchart TD
    Start([Inkomende e-mail in mailbox]) --> Fetch[GLPI MailCollector haalt berichtheaders op]
    Fetch --> Build[GLPI bouwt ticket-datastructuur]
    Build --> HookPreAdd[GLPI vuurt pre_item_add hook af]
    
    HookPreAdd --> CheckFilter{Match met actief<br/>TicketFilter patroon?}
    
    CheckFilter -- Nee --> CoreGLPI[GLPI core maakt regulier ticket aan]
    CoreGLPI --> GLPIAccepted[GLPI markeert als accepted]
    
    CheckFilter -- Ja --> FindTarget[Zoek kandidaattickets met token<br/>Sorteer op laagste ID eerst]
    FindTarget --> CheckTarget{Kandidaat<br/>gevonden?}
    
    CheckTarget -- Nee --> CoreGLPI
    
    CheckTarget -- Ja --> CheckSender{Afzenderbeleid<br/>toegestaan?}
    
    CheckSender -- Nee --> PolicyAction{Onbekende afzender<br/>actie?}
    PolicyAction -- Nieuw ticket --> CoreGLPI
    PolicyAction -- Weigeren --> Refuse[Weigeren & markeren als refused]
    PolicyAction -- Privé reactie --> MergeAction
    
    CheckSender -- Ja --> MergeAction[Voer samenvoeging uit in TicketProcessor]
    
    subgraph Samenvoegen [TicketProcessor::processTicket]
        MergeAction --> CheckClosed{Is doelticket<br/>gesloten?}
        CheckClosed -- Ja & heropenen actief --> ReopenTicket[Heropen ticket & plaats heropen-notitie]
        CheckClosed -- Nee / heropenen uit --> AddFollowup
        ReopenTicket --> AddFollowup[Voeg originele e-mail toe als ITILFollowup]
        AddFollowup --> AddPrivateAudit[Voeg private auditnotitie toe<br/>is_private=1, _disablenotif=true]
    end
    
    AddPrivateAudit --> QueueMsg[Plaats e-mail in wachtrij:<br/>FilterEngine::$mergedMailgateMessages<br/>IMAP wordt NIET aangeraakt]
    QueueMsg --> CancelAdd[Zet $ticket->input = false<br/>Annuleer creatie duplicaatticket]
    
    CancelAdd --> GLPIAddFails[addItemFromMessage retourneert false]
    GLPIAddFails --> InterceptNotImported[Hook preNotImportedEmailAdd:<br/>Onderdruk foutrecord in glpi_notimportedemails]
    
    InterceptNotImported --> NextMail{Nog meer e-mails<br/>in deze batch?}
    GLPIAccepted --> NextMail
    
    NextMail -- Ja --> FetchNext[Volgende e-mail verwerken<br/>IMAP-status is 100% stabiel]
    FetchNext --> Build
    
    NextMail -- Nee --> BatchDone[GLPI collect loop beëindigd]
    
    subgraph Shutdown [handleMailgateShutdown]
        BatchDone --> GroupQueue[Groepeer wachtrij per collector<br/>Sorteer UID's aflopend]
        GroupQueue --> ConnectIMAP[Open dedicated verbinding per collector]
        ConnectIMAP --> MoveMails[Verplaats e-mails naar accepted folder]
        MoveMails --> CloseIMAP[Sluit verbinding met storage->close<br/>Office 365 commit & expungeert verplaatsing]
        CloseIMAP --> CleanDB[Verwijder eventuele NotImported records]
        CleanDB --> FixBanner[Corrigeer errors teller in sessiemelding]
    end
    
    FixBanner --> End([Einde run: mailbox schoon & tickets bijgewerkt])
```

---

## 3. Belangrijke Technische Componenten

### A. Geen IMAP-mutaties tijdens de loop
* **Probleem voorheen:** Het direct aanroepen van `deleteMails()` tijdens de `foreach ($messages)` veranderde de IMAP-index/volgnummers op de server. De volgende lazy-loaded e-mail crashte met `RuntimeException: the single id was not found in response`.
* **Oplossing:** `FilterEngine::evaluateTicket()` raakt de actieve IMAP-verbinding niet aan, maar slaat de metadata en het object op in `FilterEngine::$mergedMailgateMessages`.

### B. Shutdown Verplaatsing & IMAP Expunge
* **Probleem voorheen:** GLPI's eigen end-of-batch opschoning liet e-mails in de Inbox staan als er geen 'refused'-map was geconfigureerd, of sloot de verbinding niet geforceerd af waardoor Office 365 IMAP-flags niet permanent maakte.
* **Oplossing:** In `handleMailgateShutdown()` wordt voor elke betrokken collector één dedicated verbinding geopend, worden alle samengevoegde UID's in aflopende volgorde naar de `accepted`-map verplaatst (bv. `INBOX/Verwerkt`), en wordt `storage->close()` aangeroepen. Dit stuurt een expliciet `LOGOUT`-commando naar Office 365, wat de verplaatsing onmiddellijk expunget.

### C. Consistente Notificatiestijl
* Zowel de heropen-melding als de filter-auditnotitie worden aangemaakt als `ITILFollowup`:
  * `'is_private' => 1` (grijze gestreepte styling met oog-icoon in GLPI).
  * `'_disablenotif' => true` (geen e-mailnotificaties naar melders).
  * `'users_id' => 0` (neutrale systeemauteur).
