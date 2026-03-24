# 🔬 Registrony del Laboratoriony

> Sistema di gestione dei laboratori scolastici — ITT G. Enrico Fermi, Francavilla Fontana

---

## 📋 Cos'è

**Registrony del Laboratoriony** è un'applicazione web PHP/MySQL per la gestione dei laboratori di un istituto tecnico. Permette di registrare le sessioni di laboratorio, tracciare l'utilizzo dei materiali, raccogliere le firme dei docenti e gestire segnalazioni di problemi alle attrezzature.

---

## ✨ Funzionalità principali

- **Dashboard** — panoramica delle sessioni odierne, segnalazioni aperte e materiali in esaurimento
- **Sessioni di laboratorio** — registrazione con ora ingresso/uscita, classe, attività svolta e firme docenti (titolare + compresenza)
- **Materiali** — inventario per laboratorio con soglie minime e alert di esaurimento
- **Segnalazioni** — sistema di ticketing con priorità (bassa / media / alta / urgente) e stati (aperta / in lavorazione / risolta / chiusa)
- **Pannello Admin** — gestione di utenti, laboratori, classi e materiali

---

## 🌐 Demo

Il sito è live a [link]([https://ryuzakxx.github.io/registro-di-laboratorio-5BIA/])

---

## 🛠️ Stack tecnologico

| Layer | Tecnologia |
|-------|-----------|
| Backend | PHP 8+ |
| Database | MySQL 8 (via PDO) |
| Frontend | HTML5, CSS3, JavaScript vanilla |

---


## 📁 Struttura del progetto

```
registrony/
├── assets/
│   ├── css/style.css       # Stili globali
│   ├── js/app.js           # JavaScript (sidebar, modal, ecc.)
│   └── img/
├── config/
│   ├── app.php             # Configurazione base (BASE_PATH)
│   ├── auth.php            # Autenticazione e sessioni
│   └── database.php        # Connessione PDO MySQL
├── includes/
│   ├── header.php          # Layout header + sidebar
│   └── footer.php          # Layout footer
├── pages/
│   ├── admin/
│   │   ├── classi.php
│   │   ├── laboratori.php
│   │   ├── materiali.php
│   │   └── utenti.php
│   ├── materiali/
│   │   └── utilizzo.php
│   ├── segnalazioni/
│   │   ├── index.php
│   │   ├── nuova.php
│   │   └── dettaglio.php
│   └── sessioni/
│       ├── index.php
│       ├── nuova.php
│       └── dettaglio.php
├── index.php               # Dashboard
├── login.php
├── logout.php
├── registrony.sql          # Schema + dati iniziali
└── setup.php               # Script di setup alternativo
```

---

## 👥 Collaboratori

| Nome | Ruolo |
|------|-------|
| **Francesco Moretto** | Sviluppatore |
| **Daniele Signorile** | Sviluppatore |
| **Patrick Colucci** | Sviluppatore |

---

## 📌 Note di sviluppo

- Il `BASE_PATH` viene rilevato automaticamente dal percorso della cartella — la repo funziona con qualsiasi nome di directory sotto `htdocs/`
- Il pannello Admin è visibile solo agli utenti con ruolo `admin`
- Il trigger MySQL `trg_firme_max_due_insert` limita a 2 le firme per sessione
- Il trigger `trg_aggiorna_quantita_materiale` aggiorna automaticamente la giacenza dopo ogni utilizzo

---

*Progetto scolastico — ITT Enrico Fermi, Francavilla Fontana — A.S. 2025/2026*
