# AtoM AHG Framework - Architecture Diagrams

**Focus:** ahgLibraryPlugin Integration  
**Version:** 1.0.0  
**Last Updated:** 2025-01-08

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [ahgLibraryPlugin Detail](#2-ahglibraryplugin-detail)
3. [Request Lifecycle Flow](#3-request-lifecycle-flow)
4. [File Structure](#4-file-structure)
5. [Database Access Patterns](#5-database-access-patterns)
6. [Plugin Lifecycle](#6-plugin-lifecycle)
7. [Class Relationships](#7-class-relationships)
8. [Request Sequence](#8-request-sequence)

---

## 1. Architecture Overview

Shows the three-layer structure: AtoM Base, atom-framework, atom-ahg-plugins

```mermaid
flowchart TB
    subgraph CLIENT["Client Browser"]
        REQ["HTTP Request"]
    end
    subgraph NGINX["Nginx Web Server"]
        STATIC["Static Assets"]
        PHPFPM["PHP-FPM 8.3"]
    end
    subgraph ATOMBASE["AtoM 2.10 BASE Symfony 1.x"]
        direction TB
        FRONT["index.php"]
        subgraph SYMFONY["Symfony 1.x Core"]
            ROUTER["Symfony Router"]
            ACTIONS["Action Classes"]
            TEMPLATES["PHP Templates"]
            ACL["QubitAcl"]
        end
        subgraph PROPEL["Propel ORM"]
            QUBIT["Qubit Classes"]
            PEER["Peer Classes"]
        end
        CONFIG["ProjectConfiguration"]
    end
    subgraph FRAMEWORK["atom-framework REQUIRED"]
        direction TB
        BOOTSTRAP["bootstrap.php"]
        subgraph LARAVEL["Laravel Query Builder"]
            CAPSULE["Illuminate Capsule"]
            QB["Query Builder"]
            CONN["MySQL Connection"]
        end
        subgraph SERVICES["Framework Services"]
            EXTMGR["ExtensionManager"]
            EXTPROT["ExtensionProtection"]
        end
        CLI["bin/atom CLI"]
    end
    subgraph PLUGINS["atom-ahg-plugins"]
        direction TB
        subgraph LOCKED["REQUIRED Locked"]
            THEMEB5["ahgThemeB5Plugin"]
            SECCLEAR["ahgSecurityClearancePlugin"]
        end
        subgraph OPTIONAL["OPTIONAL Plugins"]
            LIB["ahgLibraryPlugin"]
            DISPLAY["ahgDisplayPlugin"]
            BACKUP["ahgBackupPlugin"]
        end
    end
    subgraph DATABASE["MySQL Database"]
        ATOMTABLES[("Core AtoM Tables")]
        PLUGINTABLE[("atom_plugin")]
        PLUGINTABLES[("Plugin Tables")]
    end
    REQ --> NGINX
    NGINX --> PHPFPM
    PHPFPM --> FRONT
    FRONT --> CONFIG
    CONFIG -->|"loadPluginsFromDatabase"| PLUGINTABLE
    CONFIG --> SYMFONY
    CONFIG --> FRAMEWORK
    BOOTSTRAP --> CAPSULE
    CAPSULE --> CONN
    CONN --> DATABASE
    SYMFONY --> PROPEL
    PROPEL --> ATOMTABLES
    EXTMGR -->|"PDO only"| PLUGINTABLE
    LOCKED --> SYMFONY
    OPTIONAL --> SYMFONY
    LIB --> QB
    QB --> PLUGINTABLES
    TEMPLATES --> THEMEB5
    ACL --> SECCLEAR
```

---

## 2. ahgLibraryPlugin Detail

Internal structure showing Configuration, Actions, Lib, Templates, and Data layers

```mermaid
flowchart TB
    subgraph USER["User Request"]
        URL["library routes"]
    end
    subgraph ATOMROUTING["AtoM Routing Layer"]
        SYMFONYROUTER["Symfony Router"]
        MODULECONFIG["ahgLibraryPlugin routing.yml"]
    end
    subgraph PLUGINSTRUCT["ahgLibraryPlugin Structure"]
        direction TB
        subgraph CONFIGLAYER["Configuration"]
            PLUGINCONFIG["PluginConfiguration.class.php"]
            EXTJSON["extension.json"]
            ROUTINGYML["config routing.yml"]
        end
        subgraph ACTIONSLAYER["Actions Controllers"]
            INDEXACT["indexAction"]
            BROWSEACT["browseAction"]
            VIEWACT["viewAction"]
            EDITACT["editAction"]
        end
        subgraph LIBLAYER["lib Classes"]
            REPO["LibraryRepository.php"]
            SVC["LibraryService.php"]
            HELPER["LibraryHelper.php"]
            FORM["LibraryForm.class.php"]
        end
        subgraph TEMPLATELAYER["templates"]
            TPLINDEX["indexSuccess.php"]
            TPLBROWSE["browseSuccess.php"]
            TPLVIEW["viewSuccess.php"]
            PARTIALS["partials"]
        end
        subgraph DATALAYER["data"]
            INSTALLSQL["install.sql"]
        end
    end
    subgraph FRAMEWORKLAYER["atom-framework Integration"]
        direction TB
        DBCAPSULE["Laravel Query Builder"]
        BASEREPO["BaseRepository"]
    end
    subgraph THEMELAYER["ahgThemeB5Plugin"]
        LAYOUT["layout.php Bootstrap 5"]
        NAV["Navigation Menus"]
    end
    subgraph DBTABLES["Database Tables"]
        LIBRARYTBL[("library_item")]
        LIBRARYI18N[("library_item_i18n")]
        ATOMPLUGIN[("atom_plugin")]
    end
    URL --> SYMFONYROUTER
    SYMFONYROUTER --> MODULECONFIG
    MODULECONFIG --> ACTIONSLAYER
    PLUGINCONFIG -->|"registers"| ROUTINGYML
    ACTIONSLAYER --> REPO
    REPO --> BASEREPO
    BASEREPO --> DBCAPSULE
    DBCAPSULE --> LIBRARYTBL
    ACTIONSLAYER --> TEMPLATELAYER
    TEMPLATELAYER --> LAYOUT
    INSTALLSQL -.->|"creates"| DBTABLES
    EXTJSON -.->|"metadata"| ATOMPLUGIN
```

---

## 3. Request Lifecycle Flow

Complete flow from bootstrap through plugin loading to response rendering

```mermaid
flowchart TD
    subgraph BOOT["Application Bootstrap"]
        A1["index.php"] --> A2["require ProjectConfiguration"]
        A2 --> A3["getApplicationConfiguration"]
        A3 --> A4["setup"]
    end
    subgraph PLUGINLOAD["Plugin Loading"]
        A4 --> B1{"loadPluginsFromDatabase"}
        B1 --> B2["SELECT from atom_plugin"]
        B2 --> B3["Build plugin array"]
        B3 --> B4["enablePlugins"]
        B4 --> B5["Load PluginConfiguration classes"]
    end
    subgraph FRAMEWORKINIT["Framework Init"]
        B5 --> C1["atom-framework bootstrap.php"]
        C1 --> C2["Initialize Laravel Capsule"]
        C2 --> C3["Set MySQL Connection"]
        C3 --> C4["Make Capsule global"]
    end
    subgraph ROUTING["Request Routing"]
        C4 --> D1["Symfony Router parses URL"]
        D1 --> D2{"Module exists?"}
        D2 -->|"Yes"| D3["Load module actions"]
        D2 -->|"No"| D4["404 Error"]
        D3 --> D5["Execute action method"]
    end
    subgraph ACTIONEXEC["Action Execution"]
        D5 --> E1["libraryActions.class.php"]
        E1 --> E2["Get Request Parameters"]
        E2 --> E3{"Use Repository?"}
        E3 -->|"Yes"| E4["LibraryRepository"]
        E3 -->|"No"| E5["Direct Propel"]
        E4 --> E6["Laravel Query Builder"]
        E5 --> E7["Propel ORM"]
        E6 --> E8[("MySQL")]
        E7 --> E8
        E8 --> E9["Return data to Action"]
    end
    subgraph RENDER["Template Rendering"]
        E9 --> F1["Set template variables"]
        F1 --> F2["Load xxxSuccess.php"]
        F2 --> F3["Apply theme layout"]
        F3 --> F4["Render Bootstrap 5 HTML"]
        F4 --> F5["Send Response"]
    end
```

---

## 4. File Structure

Directory structure at /usr/share/nginx/archive showing symlinks and dependencies

```mermaid
flowchart LR
    subgraph ROOT["usr share nginx archive"]
        direction TB
        subgraph ATOMCORE["AtoM Core"]
            INDEX["index.php"]
            subgraph CONFIGDIR["config"]
                PROJCONFIG["ProjectConfiguration.class.php"]
                CONFIGPHP["config.php"]
            end
            subgraph LIBDIR["lib"]
                QUBITIO["QubitInformationObject"]
                QUBITACL["QubitAcl"]
            end
        end
        subgraph FWDIR["atom-framework"]
            FWBOOT["bootstrap.php"]
            FWBIN["bin atom and install"]
            subgraph FWSRC["src"]
                EXTMGR2["ExtensionManager.php"]
                BASEREPO2["BaseRepository.php"]
                HELPERS2["IconHelper.php"]
            end
            subgraph FWCONFIG["config"]
                PROJTPL["ProjectConfiguration template"]
            end
        end
        subgraph AHGPLUGINS["atom-ahg-plugins"]
            subgraph THEMEB52["ahgThemeB5Plugin"]
                TCONFIG["Configuration.class.php"]
                TLAYOUT["templates layout.php"]
            end
            subgraph LIBPLUGIN["ahgLibraryPlugin"]
                LCONFIG["Configuration.class.php"]
                LROUTING["config routing.yml"]
                LACTIONS["modules library actions"]
                LTEMPLATES["modules library templates"]
                LREPO["lib Repositories"]
                LSQL["data install.sql"]
            end
        end
        subgraph PLUGINSSYM["plugins symlinks"]
            SYMTHEME["ahgThemeB5Plugin"]
            SYMLIB["ahgLibraryPlugin"]
        end
    end
    INDEX --> PROJCONFIG
    PROJCONFIG -->|"copies from"| PROJTPL
    SYMTHEME -.-> THEMEB52
    SYMLIB -.-> LIBPLUGIN
    LREPO -->|"extends"| BASEREPO2
    LTEMPLATES -->|"uses"| TLAYOUT
```

---

## 5. Database Access Patterns

When to use Propel vs Laravel Query Builder vs PDO

```mermaid
flowchart TB
    subgraph ACTION["Action Class Controller"]
        REQ["Request Handler"]
    end
    subgraph PROPELPATH["PROPEL ORM PATH for AtoM Core Data"]
        direction TB
        P1["QubitInformationObject getById"]
        P2["QubitQuery Criteria"]
        P3["Propel Connection"]
    end
    subgraph LARAVELPATH["LARAVEL QUERY BUILDER for Extension Data"]
        direction TB
        L1["LibraryRepository extends BaseRepository"]
        L2["DB table library_item"]
        L3["Laravel Capsule"]
    end
    subgraph PDOPATH["PDO PATH for Plugin Manager Only"]
        direction TB
        D1["ExtensionManager"]
        D2["Propel getConnection"]
        D3["PDO prepare execute"]
    end
    subgraph MYSQL["MySQL Database"]
        subgraph CORETABLES["Core AtoM Tables"]
            TOBJ[("object")]
            TINFO[("information_object")]
            TACTOR[("actor")]
        end
        subgraph FWTABLES["Framework Tables"]
            TPLUGIN[("atom_plugin")]
            TEXT[("atom_extension")]
        end
        subgraph PLUGTABLES["Plugin Tables"]
            TLIB[("library_item")]
            TLIBI18N[("library_item_i18n")]
        end
    end
    REQ -->|"AtoM entities"| PROPELPATH
    REQ -->|"Extension data"| LARAVELPATH
    REQ -->|"Plugin registry"| PDOPATH
    P1 --> P2 --> P3 --> CORETABLES
    L1 --> L2 --> L3 --> PLUGTABLES
    D1 --> D2 --> D3 --> TPLUGIN
```

### When to Use Each

| Path | Use For |
|------|---------|
| **Propel ORM** | QubitInformationObject, Actor, ACL, Core AtoM queries |
| **Laravel QB** | Plugin-specific tables, Custom reports, Complex joins |
| **PDO** | Plugin Manager ONLY due to autoloader conflict |

---

## 6. Plugin Lifecycle

Complete lifecycle: Install, Enable, Runtime, Disable, Uninstall

```mermaid
flowchart TB
    subgraph INSTALL["Installation Phase"]
        I1["git clone atom-ahg-plugins"] --> I2["bash bin install"]
        I2 --> I3["Create symlinks"]
        I3 --> I4["Run database install.sql"]
        I4 --> I5["Copy ProjectConfiguration template"]
        I5 --> I6["Load plugin data files"]
        I6 --> I7["INSERT INTO atom_plugin"]
    end
    subgraph ENABLE["Enable Phase"]
        E1["extension enable ahgLibraryPlugin"] --> E2["ExtensionManager enable"]
        E2 --> E3["UPDATE atom_plugin SET is_enabled = 1"]
        E3 --> E4["updateSymfonyPlugins"]
        E4 --> E5["Clear cache"]
    end
    subgraph RUNTIME["Runtime Phase"]
        R1["ProjectConfiguration setup"] --> R2["loadPluginsFromDatabase"]
        R2 --> R3["SELECT FROM atom_plugin WHERE enabled"]
        R3 --> R4["enablePlugins array"]
        R4 --> R5["Load PluginConfiguration"]
        R5 --> R6["Register routes actions"]
        R6 --> R7["Plugin ready"]
    end
    subgraph REQUEST["Request Handling"]
        Q1["library browse request"] --> Q2["Route matches"]
        Q2 --> Q3["Execute action"]
        Q3 --> Q4["Repository query"]
        Q4 --> Q5["Render template"]
        Q5 --> Q6["Response"]
    end
    subgraph DISABLE["Disable Phase"]
        DD1["extension disable"] --> DD2["Check is_locked"]
        DD2 --> DD3["SET is_enabled = 0"]
        DD3 --> DD4["Data preserved"]
    end
    subgraph UNINSTALL["Uninstall Phase"]
        U1["extension uninstall"] --> U2["Prompt backup"]
        U2 --> U3["Backup tables"]
        U3 --> U4["Schedule deletion 30 days grace"]
        U4 --> U5["cleanup cron"]
        U5 --> U6["DROP TABLE"]
    end
    INSTALL --> ENABLE --> RUNTIME --> REQUEST
    RUNTIME -.-> DISABLE -.-> UNINSTALL
    DISABLE -.->|"re-enable"| ENABLE
```

---

## 7. Class Relationships

UML class diagram showing inheritance and dependencies

```mermaid
classDiagram
    direction TB
    class sfActions {
        +execute
        +forward
        +redirect
        #getUser
        #getRequest
    }
    class QubitAcl {
        +check
        +hasPermission
    }
    class BaseRepository {
        #table string
        #primaryKey string
        +find
        +findAll
        +create
        +update
        +delete
        #query
    }
    class DB {
        +table
        +select
        +insert
        +update
        +delete
    }
    class ExtensionManager {
        +enable
        +disable
        +install
        +discover
    }
    class libraryActions {
        -repository LibraryRepository
        +executeIndex
        +executeBrowse
        +executeView
        +executeCreate
        +executeEdit
    }
    class LibraryRepository {
        #table library_item
        +findByCategory
        +findBySlug
        +search
        +getWithI18n
    }
    class LibraryService {
        -repository LibraryRepository
        +importFromCsv
        +exportToCsv
    }
    class LibraryHelper {
        +formatCallNumber
        +getStatusLabel
    }
    class LibraryForm {
        +configure
        +bind
        +isValid
        +save
    }
    libraryActions --|> sfActions : extends
    LibraryRepository --|> BaseRepository : extends
    libraryActions --> LibraryRepository : uses
    libraryActions --> LibraryService : uses
    libraryActions --> QubitAcl : checks
    libraryActions --> LibraryForm : form
    LibraryRepository --> DB : query builder
    LibraryService --> LibraryRepository : uses
```

---

## 8. Request Sequence

Sequence diagram for GET /library/view/some-book

```mermaid
sequenceDiagram
    autonumber
    participant Browser
    participant Nginx
    participant Index as index.php
    participant Config as ProjectConfiguration
    participant PluginDB as atom_plugin DB
    participant Router as Symfony Router
    participant Actions as libraryActions
    participant ACL as QubitAcl
    participant Repo as LibraryRepository
    participant Laravel as Laravel QB
    participant MySQL
    participant Template as viewSuccess.php
    participant Theme as ahgThemeB5Plugin

    Browser->>Nginx: GET library view some-book
    Nginx->>Index: Forward to PHP-FPM

    rect rgb(227, 242, 253)
        Note over Index,PluginDB: Bootstrap Phase
        Index->>Config: require and setup
        Config->>PluginDB: loadPluginsFromDatabase
        PluginDB-->>Config: enabled plugins array
        Config->>Config: enablePlugins
    end

    rect rgb(255, 243, 224)
        Note over Router,Actions: Routing Phase
        Index->>Router: dispatch request
        Router->>Router: match route
        Router->>Actions: executeView request
    end

    rect rgb(232, 245, 233)
        Note over Actions,ACL: Authorization
        Actions->>ACL: check read library
        ACL-->>Actions: true
    end

    rect rgb(243, 229, 245)
        Note over Actions,MySQL: Data Retrieval
        Actions->>Repo: findBySlug some-book
        Repo->>Laravel: DB table where slug
        Laravel->>MySQL: SELECT query
        MySQL-->>Laravel: ResultSet
        Laravel-->>Repo: Collection
        Repo-->>Actions: LibraryItem
    end

    rect rgb(255, 249, 196)
        Note over Actions,Theme: Rendering
        Actions->>Template: render with data
        Template->>Theme: extend layout
        Theme-->>Template: Rendered HTML
        Template-->>Actions: Complete HTML
    end

    Actions-->>Router: Response
    Router-->>Index: Response
    Index-->>Nginx: Response
    Nginx-->>Browser: HTML Page
```

---

## Quick Reference

### Key Components

| Component | Technology | Purpose |
|-----------|------------|---------|
| Core AtoM | Symfony 1.x + Propel | Legacy base system |
| atom-framework | Laravel Capsule | Modern database operations |
| Plugin Loading | atom_plugin table | Database-driven plugin discovery |
| Required Plugins | ahgThemeB5Plugin, ahgSecurityClearancePlugin | Locked, always enabled |
| ahgLibraryPlugin | Symfony actions + Laravel repositories | Library management extension |

