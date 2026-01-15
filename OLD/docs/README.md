# TDD System Documentation

Welcome to the TDD (Tilstandsrapport og Drift-dokumentation) System documentation.

## 📚 Documentation Index

### Core Guides
| Document | Description |
|----------|-------------|
| [API_USAGE_GUIDE.md](API_USAGE_GUIDE.md) | API endpoints and usage |
| [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) | How to deploy the system |
| [TESTING_GUIDE.md](TESTING_GUIDE.md) | Testing procedures |
| [CODE_ORGANIZATION.md](CODE_ORGANIZATION.md) | Project structure |
| [I18N_DOCUMENTATION.md](I18N_DOCUMENTATION.md) | Internationalization |
| [LOG_MANAGEMENT.md](LOG_MANAGEMENT.md) | Logging configuration |

### Status & Progress
| Document | Description |
|----------|-------------|
| [SYSTEM_STATUS.md](SYSTEM_STATUS.md) | Current system status |
| [OPTIMIZATION_PROGRESS.md](OPTIMIZATION_PROGRESS.md) | Optimization work |
| [CHANGELOG_OPTIMIZATION.md](CHANGELOG_OPTIMIZATION.md) | Changes log |
| [CODE_OPTIMIZATION_REPORT.md](CODE_OPTIMIZATION_REPORT.md) | Latest code audit |

### Archive
Historical reports and completed work:
- `archive/audits/` - System audit reports
- `archive/fixes/` - Bug fix reports
- `archive/phases/` - Phase completion reports

## 🚀 Quick Start

1. **Clone & Configure**
   ```bash
   cp config.sample.php config.php
   # Edit config.php with your database settings
   ```

2. **Database Setup**
   ```bash
   php migrations/run_all_migrations.php
   ```

3. **Access System**
   - Default login: `admin` / `admin123`
   - URL: `https://your-domain/?module=Dashboard`

## 🏗️ Architecture Overview

```
sys_tdd/
├── core/           # Framework core classes
├── modules/        # Application modules (MVC pattern)
├── assets/         # CSS, JavaScript, images
├── migrations/     # Database migrations
├── docs/           # Documentation (this folder)
└── logs/           # Application logs
```

## 📖 Key Modules

- **Dashboard** - System overview and stats
- **Project** - Project management
- **BuildingElement** - Building inspection elements
- **Report** - Report generation with templates
- **Admin** - System administration

---

*Last updated: 2026-01-14*
