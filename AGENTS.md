# Agent guidelines — venusian/framework

## Knowledge Bundle (OKF)

This package ships an Open Knowledge Format bundle at [`.okf/`](.okf/) (excluded from Composer dist via `.gitattributes` `export-ignore`).

Before changing framework code or advising on Venusian architecture **for this package**:

1. Read [`.okf/index.md`](.okf/index.md) first (progressive disclosure).
2. Open only the linked concepts needed for the task.
3. Prefer `status: stable` concepts; treat `deprecated` as historical only. Concepts in this bundle are human-verified `stable` unless marked `deprecated`.
4. When you learn something durable about **this package**, update the affected `.okf` concept(s) and append `.okf/log.md`. New/changed concepts stay `status: draft` until a human verifies them.
5. Do **not** create `.okf` folders under `src/Voyager/*` (component folders) — knowledge for this package lives at the package root only.

## Package rules (quick) — 0.8.x (reconstituting)
- Composer: `venusian/framework` **0.8.0**. PHP `^8.4|^8.5`.
- Namespace root is `Voyager\`. Components live under `src/Voyager/*`.
-  **Dependency direction**
  - **System** may be aware of everything.
  - **Nothing below System** depends on System
  - **NutsAndBolts** may depend on its sibling packages (Collections, Conditionable, Macroable, Reflection, …) **and `voyager/contracts`** — not other components.
  - **Other components** can depend on NutsAndBolts; peer deps can depend on other peep deps when justified (e.g. Broadcasting ↔ Filesystem for `.env` install writes); never System.