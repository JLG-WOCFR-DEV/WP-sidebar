# Presets graphiques et composants UI

Cette note recense différents ensembles de composants ("presets") graphiques comparables à Headless UI, Shadcn UI, Radix UI, Bootstrap, Semantic UI ou Anime.js. Ils sont regroupés par typologie afin de faciliter la sélection selon le niveau d'opinion ou d'interactivité souhaité.

## Préréglages codés dans Sidebar JLG

Les presets suivants sont désormais disponibles dans le plugin (`DefaultSettings::STYLE_PRESETS`) et peuvent être appliqués depuis l’onglet **Style & Préréglages**.

| Clé | Inspiration | Intentions visuelles |
| --- | --- | --- |
| `headless_minimal` | Headless UI | Fond neutre clair, accent bleu accessible, effets underline centrés. |
| `shadcn_soft` | Shadcn UI | Gradient sombre velouté, violet vibrant et typographie ronde. |
| `radix_neutrals` | Radix UI | Superpositions gris anthracite, bordures subtiles et accent violet dégradé. |
| `bootstrap_classic` | Bootstrap | Fond clair texturé, bleu « primary » et alignement sobre des éléments. |
| `semantic_fresh` | Semantic UI | Palette sombre chic avec turquoise energisant et hover en tile-slide. |
| `moderne_dark` | Inspiration éditoriale | Dégradé sombre prononcé, capitales audacieuses et effets underline. |
| `minimal_light` | Inspiration minimaliste | Blanc poudré, accent bleu, transitions légères. |
| `retro_warm` | Ambiance rétro | Dégradé orangé/rose, typographie à empattements et boutons arrondis. |
| `glass_neon` | Glassmorphism | Transparence sombre, accent néon et effet glow. |
| `anime_neon` | Anime.js | Gradient violet/bleu, effet lumineux marqué et animation scale punchy. |
| `astra_modern` | Thème Astra | Marine profond, accent turquoise AA et hover souligné pour les layouts Astra. |
| `divi_sleek` | Divi | Dégradé violet/orangé, boutons pill et animation scale dynamique. |
| `bricks_neutral` | Bricks Builder | Tons sable/gris chauds et accent cuivre pour s’intégrer aux sections Bricks. |

## Librairies de composants headless

Ces solutions se concentrent sur la logique d'accessibilité et la gestion des interactions, en laissant la couche visuelle à la charge du projet.

- **React Aria** (Adobe) – Ensemble de hooks headless axé sur l'accessibilité et la compatibilité avec React Aria Components pour fournir des styles par défaut.
- **VueUse Motion** – Gestion des animations et interactions headless pour Vue, utile pour orchestrer des comportements personnalisés.
- **Downshift** – Composants headless pour les menus déroulants, autocomplete et combobox en React.

## Design systems avec styles prêts à l'emploi

Ces librairies proposent des composants pré-stylés, souvent accompagnés d'un thème configurable.

- **Mantine** – Ensemble complet de composants React avec theming avancé, support SSR et grandes capacités d'accessibilité.
- **Chakra UI** – Composants stylés et accessibles pour React, avec API de style basée sur les tokens.
- **Vuetify** – Design system Material Design pour Vue, adapté aux applications complexes.
- **PrimeVue / PrimeReact / PrimeNG** – Gamme multi-frameworks avec une large couverture de composants et thèmes personnalisables.

## Kits CSS classiques

Solutions proches de Bootstrap ou Semantic UI, fournissant une base CSS et parfois JS légère.

- **Bulma** – Framework CSS basé sur Flexbox, modulable et sans dépendance JavaScript.
- **Tailwind UI** – Composants pré-construits compatibles avec Tailwind CSS pour accélérer le prototypage.
- **Foundation** – Framework responsive historique avec grilles, composants et utilitaires.

## Moteurs d'animation et micro-interactions

Pour enrichir les interfaces avec des transitions complexes similaires à Anime.js.

- **Framer Motion** – API intuitive pour les animations React, support des gestuelles et layout animations.
- **GSAP (GreenSock Animation Platform)** – Moteur d'animation JavaScript performant pour timelines, SVG et canvas.
- **LottieFiles** – Player pour animations After Effects exportées en JSON (Lottie), facile à intégrer sur le web.

## Générateurs et builders visuels

Outils permettant de composer des interfaces via éditeurs graphiques ou configuration déclarative.

- **Builder.io** – Builder visuel headless connecté à diverses stacks front-end.
- **Plasmic** – Studio visuel pour React, Next.js et code-first, combinant design system et publication.
- **Anima** – Conversion de maquettes Figma en code React/HTML, avec prise en charge de design tokens.

## Critères de choix

- **Accessibilité** : privilégier les solutions headless ou orientées a11y (React Aria, Chakra UI, Mantine).
- **Performance** : GSAP et Framer Motion offrent un contrôle fin sur les performances d'animations.
- **Personnalisation** : Tailwind UI, Mantine et Prime* disposent de thèmes extensibles ; les kits headless restent les plus flexibles.
- **Écosystème** : sélectionner selon le framework (React, Vue, Angular) et la compatibilité avec l'outillage existant (Storybook, Design Tokens, CSS-in-JS).

Ces presets couvrent différents niveaux d'opinion et de personnalisation, offrant une base pour constituer un environnement graphique adapté aux besoins du projet.

## Backlog de presets à produire

- **`marketing_cta`** – Accent corail, gros CTA arrondi et animations d'entrée progressives pour pages de capture.
- **`minimal_darkglass`** – Variante sombre inspirée du glassmorphism avec accent cyan, pensée pour les sites SaaS nocturnes.

Chaque preset devra inclure :

1. Un aperçu statique (image ou story) pour l'onglet **Style & Préréglages**.
2. La liste des variables CSS associées (`STYLE_VARIABLE_MAP`) et les valeurs par défaut.
3. Les recommandations de contraste (clair/sombre) et la configuration du bouton hamburger.
