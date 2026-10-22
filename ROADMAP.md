# Unstoppable Product Addons - Development Roadmap

This document outlines the planned development direction for Unstoppable Product Addons. Items are organized by priority and timeline.

---

## Immediate Priorities

### GitHub Migration & Documentation
- **Description:** Establish GitHub as the primary development home with proper documentation and CI/CD workflows.
- **Benefit:** Streamlined development process, automated deployments, better collaboration.
- **Priority:** High
- **Complexity:** Small
- **Milestone:** v1.1.0
- **Status:** Completed
- **Source:** Project Setup

### WordPress 7.1.3 Compatibility Verification
- **Description:** Verify and document compatibility with WordPress 7.1.3.
- **Benefit:** Ensures plugin works with latest WordPress version.
- **Priority:** High
- **Complexity:** Small
- **Milestone:** v1.0.8
- **Status:** In Progress
- **Source:** Code Review

### SVN Deployment Workflow Verification
- **Description:** Validate the GitHub Actions workflow for WordPress.org SVN deployment is functioning correctly.
- **Benefit:** Reliable automated releases to WordPress.org.
- **Priority:** High
- **Complexity:** Small
- **Milestone:** v1.1.0
- **Status:** Completed
- **Source:** Project Setup

---

## Short-Term Improvements (v1.1.x - v1.2.x)

### Admin UI Polish
- **Description:** Improve the admin interface with better visual feedback, loading states, and smoother drag-and-drop reordering.
- **Benefit:** Better user experience for store administrators.
- **Priority:** Medium
- **Complexity:** Small
- **Milestone:** v1.1.0
- **Status:** Proposed
- **Source:** Code Review

### Bulk Actions for Add-ons
- **Description:** Add bulk enable/disable and bulk delete functionality for managing multiple add-ons at once.
- **Benefit:** Saves time when managing many add-ons.
- **Priority:** Medium
- **Complexity:** Small
- **Milestone:** v1.1.0
- **Status:** Proposed
- **Source:** New Suggestion

### Enhanced Import/Export
- **Description:** Improve the import/export system with JSON format support, selective export, and cross-site compatibility validation.
- **Benefit:** Easier migration between sites and backup capabilities.
- **Priority:** Medium
- **Complexity:** Medium
- **Milestone:** v1.2.0
- **Status:** Proposed
- **Source:** Code Review

### Additional Date Picker Options
- **Description:** Add date range restrictions (min/max dates), blocked dates, and recurring availability patterns.
- **Benefit:** Better support for appointment booking and delivery scheduling use cases.
- **Priority:** Medium
- **Complexity:** Medium
- **Milestone:** v1.2.0
- **Status:** Proposed
- **Source:** New Suggestion

### Color Picker Improvements
- **Description:** Add preset color palettes, recently used colors, and optional hex code input.
- **Benefit:** More flexible color selection for customizable products.
- **Priority:** Low
- **Complexity:** Small
- **Milestone:** v1.2.0
- **Status:** Proposed
- **Source:** New Suggestion

---

## Mid-Term Feature Development (v1.3.x - v2.0.0)

### Conditional Logic System
- **Description:** Show/hide add-ons and options based on customer selections. Build a visual rule builder for creating conditions.
- **Benefit:** Creates dynamic, personalized product configuration experiences.
- **Priority:** High
- **Complexity:** Large
- **Milestone:** v2.0.0
- **Status:** Proposed
- **Source:** Previously Discussed

### Formula-Based Pricing
- **Description:** Calculate add-on prices using formulas based on product dimensions, quantities, or other input values.
- **Benefit:** Enables complex pricing scenarios like per-square-foot pricing.
- **Priority:** High
- **Complexity:** Large
- **Milestone:** v2.0.0
- **Status:** Proposed
- **Source:** Previously Discussed

### Image Swatches Enhancement
- **Description:** Expand image swatch functionality with larger preview images, zoom on hover, and gallery mode.
- **Benefit:** Better visual product customization experience.
- **Priority:** Medium
- **Complexity:** Medium
- **Milestone:** v1.3.0
- **Status:** Proposed
- **Source:** Previously Discussed

### Add-on Templates
- **Description:** Save and reuse add-on configurations as templates that can be applied to multiple products.
- **Benefit:** Dramatically reduces setup time for stores with similar products.
- **Priority:** Medium
- **Complexity:** Medium
- **Milestone:** v1.3.0
- **Status:** Proposed
- **Source:** New Suggestion

### REST API Expansion
- **Description:** Expand the REST API to support full CRUD operations for add-ons, enabling headless commerce and mobile app integrations.
- **Benefit:** Supports modern development workflows and third-party integrations.
- **Priority:** Medium
- **Complexity:** Medium
- **Milestone:** v1.4.0
- **Status:** Proposed
- **Source:** Code Review

### Character/Word Limits for Text Fields
- **Description:** Add configurable minimum and maximum character or word counts for text input fields.
- **Benefit:** Better control over customer input for personalization options.
- **Priority:** Medium
- **Complexity:** Small
- **Milestone:** v1.3.0
- **Status:** Proposed
- **Source:** New Suggestion

---

## Long-Term Development (v2.x+)

### Visual Product Builder
- **Description:** Frontend visual builder allowing customers to see their customizations in real-time on a product image.
- **Benefit:** Premium product customization experience that competes with specialized solutions.
- **Priority:** High
- **Complexity:** Large
- **Milestone:** v2.5.0
- **Status:** Proposed
- **Source:** Previously Discussed

### Multi-Step Add-on Wizard
- **Description:** Guide customers through complex product configurations with a step-by-step wizard interface.
- **Benefit:** Reduces cart abandonment for products with many options.
- **Priority:** Medium
- **Complexity:** Large
- **Milestone:** v2.0.0
- **Status:** Proposed
- **Source:** New Suggestion

### Subscription/Recurring Add-ons
- **Description:** Support for add-ons that apply to subscription products (WooCommerce Subscriptions integration).
- **Benefit:** Expands plugin use cases to subscription-based businesses.
- **Priority:** Medium
- **Complexity:** Large
- **Milestone:** v2.5.0
- **Status:** Proposed
- **Source:** New Suggestion

### Advanced Inventory Management
- **Description:** Multi-location inventory tracking, low stock notifications, and automatic option hiding when out of stock.
- **Benefit:** Better inventory control for stores with complex stock management needs.
- **Priority:** Medium
- **Complexity:** Large
- **Milestone:** v2.0.0
- **Status:** Proposed
- **Source:** Code Review

### PDF Generation for Customizations
- **Description:** Generate PDF summaries of product customizations for print-on-demand, manufacturing, or customer records.
- **Benefit:** Streamlines fulfillment workflows for customized products.
- **Priority:** Low
- **Complexity:** Medium
- **Milestone:** v2.5.0
- **Status:** Proposed
- **Source:** New Suggestion

### Analytics Dashboard
- **Description:** Track add-on usage, revenue attribution, and popular option combinations.
- **Benefit:** Data-driven insights for optimizing product offerings.
- **Priority:** Low
- **Complexity:** Large
- **Milestone:** v3.0.0
- **Status:** Proposed
- **Source:** New Suggestion

---

## Pro Features (Separate Plugin)

The following features are planned for the Pro add-on plugin, distributed separately from WordPress.org:

### Priority Support & Updates
- License-gated access to priority support and automatic updates via Base44 infrastructure.

### Advanced Conditional Logic
- Complex multi-condition rules with AND/OR logic.

### Formula Pricing Calculator
- Visual formula builder with variables and mathematical operations.

### Product Bundles Integration
- Create add-on-based product bundles with combined pricing.

### Custom CSS/Styling Options
- Per-add-on styling customization (Pro only per WordPress.org guidelines).

---

## Contributing

Feature requests and bug reports can be submitted via GitHub Issues. When suggesting a feature, please include:

1. A clear description of the feature
2. The problem it solves
3. Example use cases
4. Any relevant mockups or references

---

## Changelog Integration

When features are completed, they will be documented in:
- `readme.txt` (WordPress.org changelog)
- `CHANGELOG.md` (GitHub changelog)
- Release notes on GitHub Releases

---

*Last updated: October 2026*
