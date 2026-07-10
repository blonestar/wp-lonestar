# Block Types

Lonestar discovers block metadata from the parent theme, active child theme, and enabled folder modules. A child block with the same registered `name` wins regardless of implementation family.

## Selection matrix

| Family         | Directory          | Editor                              | Frontend                 | Use when                                                   |
| -------------- | ------------------ | ----------------------------------- | ------------------------ | ---------------------------------------------------------- |
| ACF            | `blocks/acf/`      | ACF fields                          | PHP template             | Editors need advanced field UI or relationships            |
| Native static  | `blocks/native/`   | JavaScript `edit`                   | JavaScript `save` markup | Markup is stable and belongs in post content               |
| Native dynamic | `blocks/native/`   | JavaScript `edit`                   | `render.php`             | Output depends on server state or changing data            |
| PHP-only       | `blocks/php-only/` | WordPress 7 auto-generated controls | `render.php`             | Simple server-rendered attributes need no block JavaScript |

## Contracts

- Every block has valid `block.json` metadata and a unique `namespace/slug` name.
- ACF blocks declare `acf.renderTemplate`; an optional `fields.php` returns a local field-group array. They remain visible as unavailable when ACF Pro is absent.
- Native blocks declare `editorScript`. A native block with `render`/`render.php` is dynamic; without it, it must provide a real `save` implementation and deprecations when saved markup changes.
- PHP-only blocks declare `supports.autoRegister: true` and `render: file:./render.php`, and must not declare `editorScript`.
- PHP-only controls support simple unsourced scalar attributes. They do not support `InnerBlocks`; use a native block for nested content.
- Dynamic renderers use `get_block_wrapper_attributes()` and escape at output.

Reference implementations ship as `example-acf`, `example-native`, `example-native-static`, and `example-php-only`.

## Block CSS

- All block families may ship direct CSS. JavaScript is not required merely to style a block.
- Native block CSS may use standards-based nesting; `example-native-static.css` is the reference syntax.
- Vite applies `postcss-nesting` and then Autoprefixer using the WordPress Browserslist targets.
- CSS imports are handled by Vite. Do not add `postcss-import`.
- Do not use Sass-like `postcss-nested` extensions or Tailwind directives/utilities. Prefer `theme.json` tokens, native CSS custom properties, and block-scoped selectors.
