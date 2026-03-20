# Architecture: statssearch

## Purpose

A PrestaShop statistics module that logs and reports on front-office search queries, surfacing the most popular search terms and those that returned no results.

## Directory Structure

```
statssearch.php   - Module class (ModuleGrid subclass); hook listener + grid render
upgrade/          - Migration scripts
tests/            - PHPUnit test stubs and PHPStan bootstrap
translations/     - Locale string overrides
```

## Key Design Decisions

- **Dual role**: The module both captures search events (via `hookActionSearch`) and displays aggregated results (via `hookDisplayAdminStatsModules`).
- **ModuleGrid rendering**: The report is presented as a sortable, pageable grid with CSV export.

## Extension Points

- Override `hookActionSearch` to capture additional context (e.g., customer segment, session ID).
- Add a "no results" tab to the grid by filtering `count = 0` in `getData()`.

## Dependency Flow

```
statssearch (ModuleGrid)
  └─> hookActionSearch()             — records each search query
  └─> hookDisplayAdminStatsModules() — renders the search terms grid
  └─> getData()                      — aggregated search query SQL
        └─> Db::getInstance()
```
