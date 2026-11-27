<?php
/**
 * Plain-English explanations for common axe-core rules.
 *
 * @package IWorksIE\AccessibilityChecker
 */

namespace IWorksIE\AccessibilityChecker\Support;

/**
 * Title, "why it matters" and WordPress-specific "how to fix" per rule.
 */
final class RuleGuide {

	/**
	 * Guidance for a rule, falling back to axe's own text.
	 *
	 * @param string              $rule_id axe rule id.
	 * @param array<string,mixed> $meta    Stored rule metadata (help, description).
	 * @return array{title:string,why:string,fix:string,known:bool}
	 */
	public static function get( $rule_id, array $meta = array() ) {
		$all = self::all();
		if ( isset( $all[ $rule_id ] ) ) {
			return array(
				'title' => $all[ $rule_id ][0],
				'why'   => $all[ $rule_id ][1],
				'fix'   => $all[ $rule_id ][2],
				'known' => true,
			);
		}
		$help = isset( $meta['help'] ) ? (string) $meta['help'] : '';
		$desc = isset( $meta['description'] ) ? (string) $meta['description'] : '';
		return array(
			'title' => '' !== $help ? $help : $rule_id,
			'why'   => $desc,
			'fix'   => __( 'Open the technical guidance link below for the exact requirement. If the element comes from a plugin or theme, ask its developer to fix it.', 'iworksie-accessibility-checker' ),
			'known' => false,
		);
	}

	/**
	 * Rule id => [ title, why, fix ].
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	private static function all() {
		$alt_fix = __( 'In the block editor select the image and fill in "Alternative text" in the block settings, or edit the image in Media Library. Describe what the image shows or does. If it is purely decorative, leave the alt text empty on purpose (in code: alt="").', 'iworksie-accessibility-checker' );
		$name    = __( 'Screen readers announce this control with no name, so people who cannot see it do not know what it does.', 'iworksie-accessibility-checker' );

		return array(
			'image-alt'                   => array(
				__( 'Images are missing alternative text', 'iworksie-accessibility-checker' ),
				__( 'Blind and low-vision visitors use screen readers that read alt text aloud. Without it they hear the file name or nothing at all.', 'iworksie-accessibility-checker' ),
				$alt_fix,
			),
			'input-image-alt'             => array(
				__( 'Image buttons have no text alternative', 'iworksie-accessibility-checker' ),
				$name,
				__( 'Add an alt attribute to the image button that says what it does, for example alt="Search". This usually lives in a theme or plugin template.', 'iworksie-accessibility-checker' ),
			),
			'role-img-alt'                => array(
				__( 'Elements shown as images have no text alternative', 'iworksie-accessibility-checker' ),
				__( 'The element is marked as an image (role="img") but has no text describing it, so screen reader users miss its meaning.', 'iworksie-accessibility-checker' ),
				__( 'Add an aria-label describing the image, or aria-hidden="true" if it is decorative. Icon fonts and CSS background images often cause this.', 'iworksie-accessibility-checker' ),
			),
			'svg-img-alt'                 => array(
				__( 'SVG images have no text alternative', 'iworksie-accessibility-checker' ),
				__( 'Screen readers cannot describe an SVG graphic that has no title or label.', 'iworksie-accessibility-checker' ),
				__( 'Add a <title> inside the SVG or an aria-label on it. If the SVG is decorative (for example an icon next to text), add aria-hidden="true" instead.', 'iworksie-accessibility-checker' ),
			),
			'area-alt'                    => array(
				__( 'Image map areas have no text alternative', 'iworksie-accessibility-checker' ),
				__( 'Each clickable area of an image map is a link. Without alt text, screen reader users cannot tell where it goes.', 'iworksie-accessibility-checker' ),
				__( 'Give every <area> element an alt attribute naming its destination, or replace the image map with normal links.', 'iworksie-accessibility-checker' ),
			),
			'object-alt'                  => array(
				__( 'Embedded objects have no text alternative', 'iworksie-accessibility-checker' ),
				__( 'Content embedded with <object> is invisible to screen readers unless it has a text alternative.', 'iworksie-accessibility-checker' ),
				__( 'Add an aria-label or title to the <object>, or provide the same content as text or a link next to it.', 'iworksie-accessibility-checker' ),
			),
			'image-redundant-alt'         => array(
				__( 'Alt text repeats the text next to the image', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users hear the same words twice, which is tiring and confusing.', 'iworksie-accessibility-checker' ),
				__( 'Change the image alt text so it adds information, or leave it empty if the caption or link text already says everything.', 'iworksie-accessibility-checker' ),
			),
			'color-contrast'              => array(
				__( 'Text does not have enough colour contrast', 'iworksie-accessibility-checker' ),
				__( 'Low-contrast text is hard or impossible to read for people with low vision or colour blindness, and for everyone on a phone in sunlight. WCAG AA needs 4.5:1 for normal text and 3:1 for large text.', 'iworksie-accessibility-checker' ),
				__( 'Make the text darker or the background lighter (or the other way round). In the block editor use the block\'s Color settings, which warn about low contrast. For theme-wide colours, change them in Appearance → Editor → Styles or the Customizer.', 'iworksie-accessibility-checker' ),
			),
			'link-name'                   => array(
				__( 'Links have no text', 'iworksie-accessibility-checker' ),
				__( 'A link with no readable text is announced as just "link", so keyboard and screen reader users cannot tell where it goes. This often happens with icon-only social links or linked images with no alt text.', 'iworksie-accessibility-checker' ),
				__( 'Add visible link text, or alt text on the linked image, or an aria-label on the link (for example aria-label="iWorks on LinkedIn"). For Social Icons blocks, fill in each icon\'s label.', 'iworksie-accessibility-checker' ),
			),
			'link-in-text-block'          => array(
				__( 'Links inside text can only be spotted by colour', 'iworksie-accessibility-checker' ),
				__( 'People who cannot tell colours apart will not notice a link that differs from the surrounding text only by colour.', 'iworksie-accessibility-checker' ),
				__( 'Underline links inside paragraphs (most themes allow this in Styles → Elements → Links), or make sure the link colour has 3:1 contrast with the text and changes on hover and focus.', 'iworksie-accessibility-checker' ),
			),
			'button-name'                 => array(
				__( 'Buttons have no accessible name', 'iworksie-accessibility-checker' ),
				$name,
				__( 'Give the button visible text, or an aria-label describing what it does (for example aria-label="Open menu"). Icon-only buttons in headers, sliders and pop-ups are the usual culprits.', 'iworksie-accessibility-checker' ),
			),
			'input-button-name'           => array(
				__( 'Input buttons have no text', 'iworksie-accessibility-checker' ),
				$name,
				__( 'Add a value attribute (the button text) to the <input type="submit|button|reset">, or an aria-label.', 'iworksie-accessibility-checker' ),
			),
			'label'                       => array(
				__( 'Form fields have no label', 'iworksie-accessibility-checker' ),
				__( 'Without a label, screen reader users do not know what to type into the field. Placeholder text is not a label: it disappears once typing starts.', 'iworksie-accessibility-checker' ),
				__( 'In your form plugin, make sure every field has a label (you can visually hide it if the design needs that, but do not delete it). In code, connect a <label for="…"> to the field\'s id.', 'iworksie-accessibility-checker' ),
			),
			'select-name'                 => array(
				__( 'Drop-down lists have no label', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users hear "combo box" with no idea what they are choosing.', 'iworksie-accessibility-checker' ),
				__( 'Add a label to the drop-down in your form plugin or template, or an aria-label if a visible label is not possible.', 'iworksie-accessibility-checker' ),
			),
			'form-field-multiple-labels'  => array(
				__( 'Form fields have more than one label', 'iworksie-accessibility-checker' ),
				__( 'Assistive technology may read only one of the labels, or read them in a confusing order.', 'iworksie-accessibility-checker' ),
				__( 'Keep one <label> per field. Put extra hints in a description linked with aria-describedby.', 'iworksie-accessibility-checker' ),
			),
			'autocomplete-valid'          => array(
				__( 'Form autocomplete values are invalid', 'iworksie-accessibility-checker' ),
				__( 'Correct autocomplete values let browsers fill in name, email and address fields, which helps people with motor and memory difficulties.', 'iworksie-accessibility-checker' ),
				__( 'Use a standard value such as autocomplete="email", "name" or "postal-code". Many form plugins have an autocomplete setting per field.', 'iworksie-accessibility-checker' ),
			),
			'document-title'              => array(
				__( 'The page has no title', 'iworksie-accessibility-checker' ),
				__( 'The page title is the first thing a screen reader announces and the label on the browser tab. Without it people cannot tell pages apart.', 'iworksie-accessibility-checker' ),
				__( 'Make sure the theme supports "title-tag" (add_theme_support( \'title-tag\' )) or outputs a <title>. SEO plugins also set it.', 'iworksie-accessibility-checker' ),
			),
			'html-has-lang'               => array(
				__( 'The page language is not set', 'iworksie-accessibility-checker' ),
				__( 'Screen readers use the page language to choose the right voice and pronunciation. Without it, English text may be read with the wrong accent, or the other way round.', 'iworksie-accessibility-checker' ),
				__( 'The theme\'s header should output <html <?php language_attributes(); ?>>. Check Settings → General → Site Language too.', 'iworksie-accessibility-checker' ),
			),
			'html-lang-valid'             => array(
				__( 'The page language code is not valid', 'iworksie-accessibility-checker' ),
				__( 'An unrecognised language code is as bad as none: screen readers fall back to a default voice.', 'iworksie-accessibility-checker' ),
				__( 'Use a valid code such as en, en-IE or ga. Check Settings → General → Site Language and any multilingual plugin settings.', 'iworksie-accessibility-checker' ),
			),
			'html-xml-lang-mismatch'      => array(
				__( 'The page has two different language settings', 'iworksie-accessibility-checker' ),
				__( 'When lang and xml:lang disagree, assistive technology may pick the wrong language.', 'iworksie-accessibility-checker' ),
				__( 'Remove xml:lang from the <html> element, or make it match lang.', 'iworksie-accessibility-checker' ),
			),
			'valid-lang'                  => array(
				__( 'A language code inside the page is not valid', 'iworksie-accessibility-checker' ),
				__( 'Sections in another language should be marked so they are pronounced correctly. An invalid code breaks this.', 'iworksie-accessibility-checker' ),
				__( 'Correct the lang attribute on the element shown, for example lang="ga" for Irish or lang="fr" for French.', 'iworksie-accessibility-checker' ),
			),
			'heading-order'               => array(
				__( 'Heading levels skip a step', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users jump between headings to scan a page, like a table of contents. Skipping levels (H2 straight to H4) makes the structure confusing.', 'iworksie-accessibility-checker' ),
				__( 'Choose heading levels by structure, not by size: H2 for sections, H3 inside them. Change the look with the block\'s typography settings instead of picking a smaller heading level.', 'iworksie-accessibility-checker' ),
			),
			'empty-heading'               => array(
				__( 'Headings are empty', 'iworksie-accessibility-checker' ),
				__( 'Screen readers announce an empty heading, which is confusing and wastes time.', 'iworksie-accessibility-checker' ),
				__( 'Delete empty Heading blocks, or add text. Some page builders leave empty headings behind when a widget is hidden.', 'iworksie-accessibility-checker' ),
			),
			'page-has-heading-one'        => array(
				__( 'The page has no main heading (H1)', 'iworksie-accessibility-checker' ),
				__( 'The H1 tells visitors what the page is about. Screen reader users often jump to it first.', 'iworksie-accessibility-checker' ),
				__( 'Make sure the page title is shown as an H1 (most themes do this), or add one Heading block set to H1 at the top.', 'iworksie-accessibility-checker' ),
			),
			'landmark-one-main'           => array(
				__( 'The page has no main content area', 'iworksie-accessibility-checker' ),
				__( 'A <main> landmark lets keyboard and screen reader users skip straight past the header and menu to the content.', 'iworksie-accessibility-checker' ),
				__( 'The theme should wrap the content in <main>. In block themes, set the content Group block\'s HTML element to <main> under Advanced.', 'iworksie-accessibility-checker' ),
			),
			'region'                      => array(
				__( 'Some content is outside any page region', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users navigate by regions (header, navigation, main, footer). Content outside them is easy to miss.', 'iworksie-accessibility-checker' ),
				__( 'Make sure everything sits inside <header>, <nav>, <main>, <aside> or <footer>. Cookie banners and chat widgets often cause this; ask their developer, or use the HTML element setting on Group blocks.', 'iworksie-accessibility-checker' ),
			),
			'bypass'                      => array(
				__( 'There is no way to skip to the main content', 'iworksie-accessibility-checker' ),
				__( 'Keyboard users must tab through the whole menu on every page unless there is a skip link or landmarks.', 'iworksie-accessibility-checker' ),
				__( 'Add a "Skip to content" link as the first focusable element and a <main> landmark. Most modern themes include both; older themes may not.', 'iworksie-accessibility-checker' ),
			),
			'skip-link'                   => array(
				__( 'The skip link points nowhere', 'iworksie-accessibility-checker' ),
				__( 'A "Skip to content" link whose target does not exist leaves keyboard users stuck at the top.', 'iworksie-accessibility-checker' ),
				__( 'Make the skip link\'s href (for example #content) match the id of the main content element.', 'iworksie-accessibility-checker' ),
			),
			'list'                        => array(
				__( 'Lists are built incorrectly', 'iworksie-accessibility-checker' ),
				__( 'Screen readers announce how many items a list has. Lists with other elements directly inside them break this.', 'iworksie-accessibility-checker' ),
				__( '<ul> and <ol> may only contain <li> items (and script/template tags). Usually a theme or plugin template needs fixing.', 'iworksie-accessibility-checker' ),
			),
			'listitem'                    => array(
				__( 'List items are outside a list', 'iworksie-accessibility-checker' ),
				__( 'A list item with no list around it is not announced as part of a list.', 'iworksie-accessibility-checker' ),
				__( 'Wrap <li> elements in a <ul> or <ol>.', 'iworksie-accessibility-checker' ),
			),
			'definition-list'             => array(
				__( 'Definition lists are built incorrectly', 'iworksie-accessibility-checker' ),
				__( 'Screen readers rely on correct structure to pair terms with their definitions.', 'iworksie-accessibility-checker' ),
				__( '<dl> may only contain <dt> and <dd> groups (optionally wrapped in <div>).', 'iworksie-accessibility-checker' ),
			),
			'dlitem'                      => array(
				__( 'Definition terms are outside a definition list', 'iworksie-accessibility-checker' ),
				__( 'Terms and definitions outside a <dl> lose their relationship.', 'iworksie-accessibility-checker' ),
				__( 'Wrap <dt> and <dd> elements in a <dl>.', 'iworksie-accessibility-checker' ),
			),
			'duplicate-id-aria'           => array(
				__( 'IDs used by labels are duplicated', 'iworksie-accessibility-checker' ),
				__( 'When two elements share an id that a label or ARIA attribute points to, assistive technology may announce the wrong one.', 'iworksie-accessibility-checker' ),
				__( 'Make every id unique on the page. Repeated widgets or forms shown twice (for example in a header and a footer) are the usual cause.', 'iworksie-accessibility-checker' ),
			),
			'frame-title'                 => array(
				__( 'Embedded frames have no title', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users hear "frame" with no idea whether it is a video, a map or a form.', 'iworksie-accessibility-checker' ),
				__( 'Add a title attribute to the <iframe>, for example title="Map of our office". Many embed blocks and plugins have a title setting.', 'iworksie-accessibility-checker' ),
			),
			'frame-title-unique'          => array(
				__( 'Embedded frames share the same title', 'iworksie-accessibility-checker' ),
				__( 'Identical titles make it impossible to tell embedded frames apart.', 'iworksie-accessibility-checker' ),
				__( 'Give each <iframe> a title that describes its own content.', 'iworksie-accessibility-checker' ),
			),
			'frame-focusable-content'     => array(
				__( 'Frames with interactive content cannot be reached by keyboard', 'iworksie-accessibility-checker' ),
				__( 'Keyboard users cannot reach links or buttons inside the frame.', 'iworksie-accessibility-checker' ),
				__( 'Remove tabindex="-1" from the <iframe> when it contains links, buttons or form fields.', 'iworksie-accessibility-checker' ),
			),
			'meta-viewport'               => array(
				__( 'Zooming is disabled', 'iworksie-accessibility-checker' ),
				__( 'People with low vision pinch to zoom on phones. Blocking zoom makes the site unreadable for them.', 'iworksie-accessibility-checker' ),
				__( 'Remove user-scalable=no and any maximum-scale below 2 from the viewport meta tag, usually in the theme\'s header.php or a plugin setting.', 'iworksie-accessibility-checker' ),
			),
			'meta-refresh'                => array(
				__( 'The page refreshes or redirects on a timer', 'iworksie-accessibility-checker' ),
				__( 'An automatic refresh can move people away before they finish reading, and screen readers start again from the top.', 'iworksie-accessibility-checker' ),
				__( 'Remove the meta refresh tag. Use a proper server redirect instead (for example with a redirection plugin).', 'iworksie-accessibility-checker' ),
			),
			'target-size'                 => array(
				__( 'Clickable targets are too small or too close together', 'iworksie-accessibility-checker' ),
				__( 'Small targets are hard to tap for people with tremors, large fingers or on a moving bus. WCAG 2.2 asks for at least 24 by 24 pixels or enough space around them.', 'iworksie-accessibility-checker' ),
				__( 'Increase padding on small links and buttons (pagination, social icons, carousel dots), or add space between them.', 'iworksie-accessibility-checker' ),
			),
			'scrollable-region-focusable' => array(
				__( 'Scrollable areas cannot be scrolled with the keyboard', 'iworksie-accessibility-checker' ),
				__( 'If a box scrolls but cannot receive keyboard focus, keyboard users cannot read what is hidden inside it.', 'iworksie-accessibility-checker' ),
				__( 'Add tabindex="0" and an accessible name (aria-label) to the scrolling element, or make sure it contains a focusable element.', 'iworksie-accessibility-checker' ),
			),
			'nested-interactive'          => array(
				__( 'Interactive controls are nested inside each other', 'iworksie-accessibility-checker' ),
				__( 'A button inside a link (or similar) confuses screen readers and keyboard focus: one of them is often unreachable.', 'iworksie-accessibility-checker' ),
				__( 'Restructure so links and buttons sit side by side, not inside each other. Card designs in page builders are a common cause.', 'iworksie-accessibility-checker' ),
			),
			'aria-hidden-focus'           => array(
				__( 'Hidden elements can still receive keyboard focus', 'iworksie-accessibility-checker' ),
				__( 'Keyboard users land on links or buttons that screen readers say do not exist, which is very disorienting.', 'iworksie-accessibility-checker' ),
				__( 'Either remove aria-hidden="true", or make the focusable elements inside it unfocusable (tabindex="-1") or hidden with display:none. Off-canvas menus and sliders often cause this.', 'iworksie-accessibility-checker' ),
			),
			'aria-hidden-body'            => array(
				__( 'The whole page is hidden from screen readers', 'iworksie-accessibility-checker' ),
				__( 'aria-hidden on <body> hides the entire site from assistive technology.', 'iworksie-accessibility-checker' ),
				__( 'Remove aria-hidden="true" from the <body> element. A pop-up or modal plugin usually adds it.', 'iworksie-accessibility-checker' ),
			),
			'aria-allowed-attr'           => array(
				__( 'ARIA attributes are used on the wrong elements', 'iworksie-accessibility-checker' ),
				__( 'ARIA attributes that do not belong to an element\'s role are ignored or misread by assistive technology.', 'iworksie-accessibility-checker' ),
				__( 'Remove the attribute or change the element\'s role. This is almost always in theme or plugin code; report it to the developer.', 'iworksie-accessibility-checker' ),
			),
			'aria-required-attr'          => array(
				__( 'ARIA roles are missing required attributes', 'iworksie-accessibility-checker' ),
				__( 'Some roles need extra information (for example a checkbox needs aria-checked) or screen readers cannot report their state.', 'iworksie-accessibility-checker' ),
				__( 'Add the missing attribute named in the details, or use a native HTML element (<button>, <input type="checkbox">) which provides it automatically.', 'iworksie-accessibility-checker' ),
			),
			'aria-required-children'      => array(
				__( 'ARIA widgets are missing required child elements', 'iworksie-accessibility-checker' ),
				__( 'Roles like list, menu or tablist must contain the right child roles or screen readers cannot navigate them.', 'iworksie-accessibility-checker' ),
				__( 'Add the required child roles (for example role="tab" inside role="tablist"), or remove the parent role.', 'iworksie-accessibility-checker' ),
			),
			'aria-required-parent'        => array(
				__( 'ARIA elements are missing their required parent', 'iworksie-accessibility-checker' ),
				__( 'Roles like tab or menuitem only make sense inside their container role.', 'iworksie-accessibility-checker' ),
				__( 'Wrap the elements in the required parent role, or remove their role attribute.', 'iworksie-accessibility-checker' ),
			),
			'aria-valid-attr'             => array(
				__( 'ARIA attributes are misspelled or do not exist', 'iworksie-accessibility-checker' ),
				__( 'Unknown ARIA attributes are ignored, so the information they were meant to give is lost.', 'iworksie-accessibility-checker' ),
				__( 'Correct the attribute name (for example aria-labelledby, not aria-labeledby).', 'iworksie-accessibility-checker' ),
			),
			'aria-valid-attr-value'       => array(
				__( 'ARIA attributes have invalid values', 'iworksie-accessibility-checker' ),
				__( 'An invalid value (for example aria-expanded="yes" or a reference to an id that does not exist) is ignored or misreported.', 'iworksie-accessibility-checker' ),
				__( 'Use a valid value: true/false for states, and ids that exist on the page for references.', 'iworksie-accessibility-checker' ),
			),
			'aria-roles'                  => array(
				__( 'Elements use ARIA roles that do not exist', 'iworksie-accessibility-checker' ),
				__( 'An invalid role is ignored, so the element is announced incorrectly.', 'iworksie-accessibility-checker' ),
				__( 'Correct or remove the role attribute.', 'iworksie-accessibility-checker' ),
			),
			'aria-command-name'           => array(
				__( 'ARIA buttons, links or menu items have no name', 'iworksie-accessibility-checker' ),
				$name,
				__( 'Add text inside the element or an aria-label.', 'iworksie-accessibility-checker' ),
			),
			'aria-input-field-name'       => array(
				__( 'ARIA input fields have no name', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users do not know what to enter in the field.', 'iworksie-accessibility-checker' ),
				__( 'Add an aria-label or aria-labelledby pointing to visible label text.', 'iworksie-accessibility-checker' ),
			),
			'aria-toggle-field-name'      => array(
				__( 'ARIA toggles have no name', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users hear "switch" or "checkbox" without knowing what it controls.', 'iworksie-accessibility-checker' ),
				__( 'Add visible text or an aria-label to the toggle.', 'iworksie-accessibility-checker' ),
			),
			'aria-prohibited-attr'        => array(
				__( 'ARIA labels are used where they are not allowed', 'iworksie-accessibility-checker' ),
				__( 'Some elements (like a plain <div> or <span>) cannot be named with aria-label, so the label is never announced.', 'iworksie-accessibility-checker' ),
				__( 'Move the label onto an element that supports it, give the element a suitable role, or use visible text instead.', 'iworksie-accessibility-checker' ),
			),
			'tabindex'                    => array(
				__( 'Elements force a custom keyboard order', 'iworksie-accessibility-checker' ),
				__( 'A tabindex above 0 makes focus jump around the page in an unexpected order.', 'iworksie-accessibility-checker' ),
				__( 'Remove positive tabindex values (use 0 or -1 only) and order the HTML the way it should be read.', 'iworksie-accessibility-checker' ),
			),
			'td-headers-attr'             => array(
				__( 'Table cells point to headers that do not exist', 'iworksie-accessibility-checker' ),
				__( 'Screen readers announce the column and row headers for each cell. Broken references remove that context.', 'iworksie-accessibility-checker' ),
				__( 'Make the headers attribute point to the ids of header cells in the same table, or remove it and use <th scope>.', 'iworksie-accessibility-checker' ),
			),
			'th-has-data-cells'           => array(
				__( 'Table headers have no data cells', 'iworksie-accessibility-checker' ),
				__( 'A header that describes nothing suggests the table is used for layout or built incorrectly.', 'iworksie-accessibility-checker' ),
				__( 'Remove empty header cells, or use the Table block with a header section only when the table holds data.', 'iworksie-accessibility-checker' ),
			),
			'empty-table-header'          => array(
				__( 'Table headers are empty', 'iworksie-accessibility-checker' ),
				__( 'Screen readers announce header text for every cell. An empty header gives no context.', 'iworksie-accessibility-checker' ),
				__( 'Add text to each header cell, or use a normal <td> for empty corner cells.', 'iworksie-accessibility-checker' ),
			),
			'video-caption'               => array(
				__( 'Videos have no captions', 'iworksie-accessibility-checker' ),
				__( 'Deaf and hard-of-hearing visitors, and anyone watching with the sound off, miss what is said.', 'iworksie-accessibility-checker' ),
				__( 'Add a captions track: in the Video block use "Text tracks" to upload a .vtt file. For YouTube and Vimeo, add captions on those platforms.', 'iworksie-accessibility-checker' ),
			),
			'no-autoplay-audio'           => array(
				__( 'Audio plays automatically', 'iworksie-accessibility-checker' ),
				__( 'Sound that starts on its own drowns out screen readers and is distressing for many people.', 'iworksie-accessibility-checker' ),
				__( 'Turn off autoplay on audio and video, or make it muted and provide a pause control.', 'iworksie-accessibility-checker' ),
			),
			'blink'                       => array(
				__( 'Text blinks', 'iworksie-accessibility-checker' ),
				__( 'Blinking content is distracting and can be hard to read for people with attention or vision difficulties.', 'iworksie-accessibility-checker' ),
				__( 'Remove the <blink> element.', 'iworksie-accessibility-checker' ),
			),
			'marquee'                     => array(
				__( 'Text scrolls automatically', 'iworksie-accessibility-checker' ),
				__( 'Moving text is hard to read and cannot be paused by everyone.', 'iworksie-accessibility-checker' ),
				__( 'Remove the <marquee> element, or replace it with static text.', 'iworksie-accessibility-checker' ),
			),
			'avoid-inline-spacing'        => array(
				__( 'Inline styles stop people adjusting text spacing', 'iworksie-accessibility-checker' ),
				__( 'People with dyslexia or low vision often increase line and letter spacing with a browser tool. !important inline styles block that.', 'iworksie-accessibility-checker' ),
				__( 'Remove !important from inline line-height, letter-spacing and word-spacing styles.', 'iworksie-accessibility-checker' ),
			),
			'label-content-name-mismatch' => array(
				__( 'The accessible name does not match the visible text', 'iworksie-accessibility-checker' ),
				__( 'Voice control users say the words they can see. If the hidden label is different, the command does not work.', 'iworksie-accessibility-checker' ),
				__( 'Make the aria-label start with (or contain) the visible text of the control.', 'iworksie-accessibility-checker' ),
			),
			'label-title-only'            => array(
				__( 'Form fields are labelled only with a tooltip', 'iworksie-accessibility-checker' ),
				__( 'Title tooltips are not shown on touch screens or to keyboard users, and not reliably read by screen readers.', 'iworksie-accessibility-checker' ),
				__( 'Add a proper visible <label> for the field.', 'iworksie-accessibility-checker' ),
			),
			'landmark-unique'             => array(
				__( 'Page regions of the same type have the same name', 'iworksie-accessibility-checker' ),
				__( 'Two navigation menus both announced as "navigation" are impossible to tell apart.', 'iworksie-accessibility-checker' ),
				__( 'Give each repeated region a distinct aria-label, for example "Main menu" and "Footer menu". The Navigation block has this under Advanced.', 'iworksie-accessibility-checker' ),
			),
			'presentation-role-conflict'  => array(
				__( 'Decorative elements can still be focused or are labelled', 'iworksie-accessibility-checker' ),
				__( 'An element marked as decorative but focusable or labelled is announced inconsistently.', 'iworksie-accessibility-checker' ),
				__( 'Remove role="presentation"/"none", or remove the tabindex and ARIA attributes from it.', 'iworksie-accessibility-checker' ),
			),
			'server-side-image-map'       => array(
				__( 'Server-side image maps are used', 'iworksie-accessibility-checker' ),
				__( 'Server-side image maps only work with a mouse.', 'iworksie-accessibility-checker' ),
				__( 'Replace them with normal links or a client-side image map with alt text.', 'iworksie-accessibility-checker' ),
			),
			'summary-name'                => array(
				__( 'Expandable sections have no name', 'iworksie-accessibility-checker' ),
				__( 'Screen reader users hear "collapsed" with no idea what the section contains.', 'iworksie-accessibility-checker' ),
				__( 'Give the Details block\'s summary (the clickable line) some text.', 'iworksie-accessibility-checker' ),
			),
		);
	}
}
