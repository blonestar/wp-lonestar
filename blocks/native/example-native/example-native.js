(function (wp) {
    if (!wp || !wp.blocks || !wp.blockEditor || !wp.element) {
        return;
    }

    const { registerBlockType, getBlockType } = wp.blocks;
    const { RichText, useBlockProps } = wp.blockEditor;
    const { createElement: el } = wp.element;
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
                useBlockProps({ className: "wp-block-lonestar-example-native" }),
                el(RichText, {
                    tagName: "h3",
                    value: heading,
                    placeholder: "Block heading",
                    allowedFormats: [],
                    onChange: (value) => setAttributes({ heading: value }),
                }),
                el(RichText, {
                    tagName: "p",
                    value: description,
                    placeholder: "Block description",
                    allowedFormats: ["core/bold", "core/italic", "core/link"],
                    onChange: (value) => setAttributes({ description: value }),
                })
            );
        },
        save: () => null,
    });
})(window.wp);
