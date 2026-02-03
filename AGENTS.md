# Naehwelt Shopware SageConnect

## Project Overview
This project is a **Shopware 6 Plugin** (`naehwelt/shopware-sage-connect`) designed to integrate Shopware with **Sage 100**. It functions primarily through file-based data exchange (imports/exports) and utilizes Shopware's Message Queue for asynchronous processing.

## Architecture & Key Concepts
*   **Plugin Class**: `Naehwelt\Shopware\SageConnect` (`src/SageConnect.php`)
    *   Acts as the main entry point.
    *   Implements a `CompilerPass` to dynamically register **Directory Handlers** based on the `sage_connect.directory.handlers` container parameter.
*   **Directory Handlers**: The core logic monitors specific directories for files to import/export using defined profiles.
*   **Message Queue**: Custom handlers in `src/MessageQueue/` (e.g., `EveryFiveMinutesHandler`) trigger these directory checks periodically.
*   **Import/Export Profiles**: YAML-based profiles located in `src/Resources/profiles/` define how data is mapped between Sage and Shopware.

## Key Files & Directories
*   **`src/SageConnect.php`**: Plugin boot logic and compiler pass for registering directory handlers.
*   **`src/InstallService.php`**: Handles plugin installation/updates, creating necessary import/export profile entities.
*   **`src/MessageQueue/`**: Contains scheduled task handlers (`EveryFiveMinutes`, `EveryHour`, etc.).
*   **`src/Resources/config/services.php`**: Dependency Injection configuration.
*   **`src/Resources/profiles/`**: Import/Export profile definitions (e.g., `product.yaml`).

## Installation & Usage
This plugin is typically installed as a **Git Submodule** in a Shopware project.

### Setup
1.  **Add Submodule**:
    ```bash
    git submodule add -b main https://github.com/naehwelt-flach/shopware-sage-connect.git custom/static-plugins/NaehweltSageConnect/
    ```
2.  **Install & Activate**:
    ```bash
    bin/console plugin:install SageConnect --activate
    ```
3.  **Message Queue**:
    The plugin relies on the message queue. Ensure the consumer is running:
    ```bash
    bin/console messenger:consume async --limit 1000 &
    ```

### Manual Import
You can trigger imports manually using the `import:entity` command:
```bash
bin/console import:entity --printErrors \
  --profile-technical-name=sage_connect_import_export_profile_yaml \
  custom/plugins/NaehweltSageConnect/src/Resources/profiles/product.yaml now
```

## Development Conventions
*   **Namespace**: `Naehwelt\Shopware\` mapped to `src/`.
*   **Configuration**: Prefer `services.php` for DI configuration over XML.
*   **Extending**: To add new import types, define a profile in `src/Resources/profiles/` and register a directory handler in `services.php` via the `sage_connect.directory.handlers` parameter.
