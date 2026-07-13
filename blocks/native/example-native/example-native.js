(function (wp) {
    if (!wp || !wp.blocks || !wp.blockEditor || !wp.element || !wp.i18n) {
        return;
    }

    const { registerBlockType, getBlockType } = wp.blocks;
    const { RichText, useBlockProps } = wp.blockEditor;
    const { createElement: el } = wp.element;
    const { __ } = wp.i18n;
    const blockName = "lonestar/example-native";

    if (getBlockType(blockName)) {
        return;
    }

    registerBlockType(blockName, {
        edit: (props) => {
            const { attributes, setAttributes } = props;
            const heading = attributes.heading || "";
            const description = attributes.description || "";

            return el(
                "div",
                useBlockProps({
                    className: "wp-block-lonestar-example-native",
                }),
                el(RichText, {
                    tagName: "h3",
                    value: heading,
                    placeholder: __("Block heading", "lonestar"),
                    allowedFormats: [],
                    onChange: (value) => setAttributes({ heading: value }),
                }),
                el(RichText, {
                    tagName: "p",
                    value: description,
                    placeholder: __("Block description", "lonestar"),
                    allowedFormats: ["core/bold", "core/italic", "core/link"],
                    onChange: (value) => setAttributes({ description: value }),
                }),
            );
        },
        save: () => null,
    });
})(window.wp);
