(function (blocks, element, components, blockEditor, i18n) {
    const el = element.createElement;
    const InspectorControls = blockEditor.InspectorControls;
    const PanelBody = components.PanelBody;
    const RangeControl = components.RangeControl;
    const ToggleControl = components.ToggleControl;

    const register = (name, title, description, attributes) => {
        blocks.registerBlockType(`rrp/${name}`, {
            title,
            icon: "star-filled",
            category: "widgets",
            attributes,
            edit: function (props) {
                return el(
                    "div",
                    { className: "rrp-block-placeholder" },
                    el(
                        InspectorControls,
                        {},
                        el(
                            PanelBody,
                            { title: i18n.__("Display Settings", "reevuu-reviews"), initialOpen: true },
                            attributes.limit
                                ? el(RangeControl, {
                                      label: i18n.__("Limit", "reevuu-reviews"),
                                      value: props.attributes.limit || 0,
                                      min: 0,
                                      max: 30,
                                      onChange: function (value) {
                                          props.setAttributes({ limit: value });
                                      },
                                  })
                                : null,
                            attributes.show_search
                                ? el(ToggleControl, {
                                      label: i18n.__("Show search", "reevuu-reviews"),
                                      checked: "1" === String(props.attributes.show_search || "1"),
                                      onChange: function (checked) {
                                          props.setAttributes({ show_search: checked ? "1" : "0" });
                                      },
                                  })
                                : null,
                            attributes.show_sort
                                ? el(ToggleControl, {
                                      label: i18n.__("Show sort", "reevuu-reviews"),
                                      checked: "1" === String(props.attributes.show_sort || "1"),
                                      onChange: function (checked) {
                                          props.setAttributes({ show_sort: checked ? "1" : "0" });
                                      },
                                  })
                                : null
                        )
                    ),
                    el("strong", {}, title),
                    el("p", {}, description)
                );
            },
            save: function () {
                return null;
            },
        });
    };

    register(
        "form",
        i18n.__("Review Form", "reevuu-reviews"),
        i18n.__("Displays the public review submission form.", "reevuu-reviews"),
        {
            target_id: { type: "number", default: 0 },
        }
    );

    register(
        "summary",
        i18n.__("Review Summary", "reevuu-reviews"),
        i18n.__("Displays the aggregate rating summary and distribution bars.", "reevuu-reviews"),
        {
            target_id: { type: "number", default: 0 },
        }
    );

    register(
        "list",
        i18n.__("Review List", "reevuu-reviews"),
        i18n.__("Displays the searchable, sortable reviews table.", "reevuu-reviews"),
        {
            target_id: { type: "number", default: 0 },
            limit: { type: "number", default: 0 },
            show_search: { type: "string", default: "1" },
            show_sort: { type: "string", default: "1" },
        }
    );

    register(
        "slider",
        i18n.__("Review Slider", "reevuu-reviews"),
        i18n.__("Displays reviews in a horizontal slider.", "reevuu-reviews"),
        {
            target_id: { type: "number", default: 0 },
            limit: { type: "number", default: 0 },
        }
    );

    register(
        "chips",
        i18n.__("Review Chips", "reevuu-reviews"),
        i18n.__("Displays compact review chips.", "reevuu-reviews"),
        {
            target_id: { type: "number", default: 0 },
            limit: { type: "number", default: 0 },
        }
    );

    register(
        "gallery",
        i18n.__("Review Gallery", "reevuu-reviews"),
        i18n.__("Displays a customer image gallery from approved reviews.", "reevuu-reviews"),
        {
            target_id: { type: "number", default: 0 },
            limit: { type: "number", default: 0 },
        }
    );
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
