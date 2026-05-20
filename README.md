# Hingenia Insignias Digitales

Plugin de WordPress + Tutor LMS para emitir insignias digitales (badges) a estudiantes, con QR de verificación y perfil público.

## Estado

- **0.1.0** — Fase 1: bootstrap, capa de datos (plantillas + emisiones), admin chrome custom, dashboard. (En curso)

Fases pendientes:
- Fase 2 — Editor visual de plantillas con preview canvas (nombre + QR).
- Fase 3 — Generador PNG (GD: base + texto + QR vendored).
- Fase 4 — Emisión manual + importación CSV.
- Fase 5 — Páginas públicas (galería del estudiante + verificación por token).
- Fase 6 — CI/CD a Hostinger.

## Arquitectura

```
hingenia-insignias/
├── hingenia-insignias.php       # bootstrap + activación (crea tablas)
├── includes/
│   ├── class-hi-data.php        # CRUD plantillas + emisiones + settings
│   └── class-hi-public.php      # rewrite rules /insignias/{u} y /insignia/{t}
├── admin/
│   ├── class-hi-admin.php       # menú + shell + enqueue
│   └── views/
│       ├── dashboard.php
│       ├── plantillas.php
│       ├── emisiones.php
│       ├── importar.php
│       └── settings.php
└── assets/
    ├── admin.css                # chrome custom (sidebar oscuro, no chrome WP)
    └── admin.js
```

## Tablas

- `wp_hi_badge_templates` — una plantilla por curso (PNG base + layout JSON con posición de nombre/QR).
- `wp_hi_certificates` — cada emisión: usuario, curso, token único, PNG resultante.

## URLs públicas

- `/insignias/{slug-usuario}` → galería de todas las insignias del estudiante.
- `/insignia/{token}` → verificación + descarga.
