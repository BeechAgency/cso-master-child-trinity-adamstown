# Trinity College, Adamstown Child Theme

This is the child theme for Trinity College, Adamstown, built on top of the [CSO Master Theme](https://github.com/BeechAgency/cso-master). It customizes the visual identity of the school website while leveraging the core functionality of the diocesan master theme.

---

## ✨ Additional Functionality

This child theme extends the master theme by applying the school's unique branding:

- **Custom Typography**: Tailored font selections to match the Trinity College identity.
- **Brand Color Palette**: Implements specific brand colors (Primary Dark, Primary Light, Secondary Dark, etc.) for use in blocks and components.
- **School Styling**: Customized Gutenberg block styles, including the `has-school-style` modifier for distinct content sections.

---

## 🔄 Theme Updater

The child theme features an automated updater that connects to GitHub, allowing for seamless updates directly through the WordPress dashboard.

### Configuration
The updater matches the local version in `style.css` against the latest release on GitHub:
- **Repository**: `cso-master-child-trinity-adamstown`
- **Username**: `BeechAgency`

---

## 🛠 How to Create a New Version (Git Release)

To deploy updates to the child theme, follow these steps:

1. **Update Version**: Open `style.css` and increment the `Version:` header (e.g., `1.0.1`).
2. **Commit & Push**:
   ```bash
   git add style.css
   git commit -m "Bump version to 1.0.1"
   git push origin master
   ```
3. **Create Git Tag**: Create a tag that matches the version number exactly.
   ```bash
   git tag 1.0.1
   git push origin 1.0.1
   ```
4. **GitHub Release**: 
   - Go to the [GitHub Repository](https://github.com/BeechAgency/cso-master-child-trinity-adamstown/releases).
   - Draft a new release using the tag you just pushed.
   - Title the release the same as the version.

---

*Maintained by Beech Agency*