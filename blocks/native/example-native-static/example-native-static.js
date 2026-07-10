(function (wp) {
    if (!wp || !wp.blocks || !wp.blockEditor || !wp.element) {
        return;
    }

    const { registerBlockType, getBlockType } = wp.blocks;
    const { RichText, useBlockProps } = wp.blockEditor;
    const { createElement: el } = wp.element;
    const blockName = "lonestar/example-native-static";

    if (getBlockType(blockName)) {
        return;
    }

    const saveMarkup = (attributes, legacyClassName = "") =>
        el(
            "section",
            useBlockProps.save({ className: legacyClassName }),
            el(RichText.Content, {
                tagName: "h3",
                value: attributes.heading || "",
            }),
            el(RichText.Content, {
                tagName: "p",
                value: attributes.description || "",
            }),
        );

    registerBlockType(blockName, {
        edit: ({ attributes, setAttributes }) =>
            el(
                "section",
                useBlockProps({
                    className: "wp-block-lonestar-example-native-static",
                }),
                el(RichText, {
                    tagName: "h3",
                    value: attributes.heading || "",
                    placeholder: "Block heading",
                    allowedFormats: [],
                    onChange: (heading) => setAttributes({ heading }),
                }),
                el(RichText, {
                    tagName: "p",
                    value: attributes.description || "",
                    placeholder: "Block description",
                    onChange: (description) => setAttributes({ description }),
                }),
            ),
        save: ({ attributes }) => saveMarkup(attributes),
        deprecated: [
            {
                attributes: {
                    heading: { type: "string", source: "html", selector: "h3" },
                    description: {
                        type: "string",
                        source: "html",
                        selector: "p",
                    },
                },
                save: ({ attributes }) =>
                    saveMarkup(attributes, "lonestar-example-native-static"),
            },
        ],
    });
})(window.wp);
