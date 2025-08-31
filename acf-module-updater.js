const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

// Configuration
const templatePartsPath = './develop/template-parts/page-modules';
const pageModulesJsonPath = './develop/acf-json/group_page_modules.json';
const acfJsonPath = './develop/acf-json';
const moduleThumbnailsPath = './develop/assets/img/module-thumbnails';
const phpFiltersPath = './develop/acf-module-filters.php';

function getModuleFiles() {
    return fs.readdirSync(templatePartsPath)
        .filter(file => path.extname(file).toLowerCase() === '.php');
}

function parseFieldDefinitions(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const regex = /<!--\s*(\[[\s\S]*?\])\s*-->/g;
    let fields = [];

    let match;
    while ((match = regex.exec(content)) !== null) {
        try {
            const fieldDefs = JSON.parse(match[1]);
            fields = fields.concat(fieldDefs);
        } catch (e) {
            console.error(`Error parsing field definitions in ${filePath}: ${e.message}`);
        }
    }

    return fields;
}

function generateConsistentKey(fieldName, moduleName) {
    const hash = crypto.createHash('md5').update(`${moduleName}_${fieldName}`).digest('hex');
    return `field_${hash.substr(0, 13)}`;
}

function createAcfField(fieldDef, moduleName, existingField = null) {
    const baseField = {
        key: existingField ? existingField.key : generateConsistentKey(fieldDef.fieldName, moduleName),
        label: fieldDef.fieldLabel || fieldDef.fieldName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()),
        name: fieldDef.fieldName,
        type: fieldDef.fieldType,
        required: fieldDef.required || 0,
        default_value: fieldDef.defaultValue || '',
        wrapper: {
            width: fieldDef.width || '',
            class: '',
            id: ''
        },
        ...fieldDef  // Include any additional properties from the field definition
    };

    if (fieldDef.fieldType === 'repeater') {
        baseField.sub_fields = [];
        baseField.layout = fieldDef.layout || 'row';
        baseField.button_label = 'Add New ' + fieldDef.fieldLabel;
        baseField.wrapper.width = '100';
    }

    return baseField;
}

function organizeFields(fields, moduleName, existingFieldsMap) {
    const topLevelFields = [];
    const fieldMap = new Map();

    fields.forEach(field => {
        const existingField = existingFieldsMap.get(field.fieldName);
        const acfField = createAcfField(field, moduleName, existingField);
        fieldMap.set(field.fieldName, acfField);

        if (field.parent) {
            const parentField = fieldMap.get(field.parent);
            if (parentField && parentField.type === 'repeater') {
                parentField.sub_fields.push(acfField);
                acfField.wrapper.width = '100';
            } else {
                console.warn(`Parent field "${field.parent}" not found or not a repeater for field "${field.fieldName}"`);
            }
        } else {
            topLevelFields.push(acfField);
        }
    });

    return topLevelFields;
}

function getModuleThumbnail(moduleName) {
    const thumbnailPath = path.join(moduleThumbnailsPath, `${moduleName}.png`);
    if (fs.existsSync(thumbnailPath)) {
        console.log('Found thumbnail:', thumbnailPath);
        return `./develop/assets/img/module-thumbnails/${moduleName}.png`;
    }
    return '';
}

function generatePhpFilters(moduleFiles) {
    let phpCode = "<?php\n";
    phpCode += "// Automatically generated ACF Extended flexible content thumbnail filters\n\n";

    moduleFiles.forEach(file => {
        const moduleName = path.basename(file, '.php');
        const thumbnailPath = `assets/img/module-thumbnails/${moduleName}.png`;

        phpCode += `add_filter('acfe/flexible/thumbnail/layout=page_modules__${moduleName}', function($thumbnail, $field, $layout) {
    return get_stylesheet_directory_uri() . '/${thumbnailPath}';
}, 10, 3);\n\n`;
    });

    fs.writeFileSync(phpFiltersPath, phpCode);
    console.log('Generated PHP filters file:', phpFiltersPath);
}

function createInitialPageModulesJson() {
    return {
        key: "group_page_modules",
        title: "Page Modules",
        fields: [
            {
                key: "field_page_modules",
                label: "Page Modules",
                name: "page_modules",
                type: "flexible_content",
                instructions: "",
                required: 0,
                conditional_logic: 0,
                wrapper: {
                    width: "",
                    class: "",
                    id: ""
                },
                layouts: {},
                button_label: "Add Module",
                min: "",
                max: "",
                display: "thumbnail",
                layout: "block",
                acfe_flexible_advanced: 1,
                acfe_flexible_stylised_button: 0,
                acfe_flexible_hide_empty_message: 0,
                acfe_flexible_empty_message: "",
                acfe_flexible_layouts_templates: 0,
                acfe_flexible_layouts_placeholder: 0,
                acfe_flexible_layouts_thumbnails: 1,
                acfe_flexible_layouts_settings: 0,
                acfe_flexible_async: [],
                acfe_flexible_add_actions: [],
                acfe_flexible_remove_button: [],
                acfe_flexible_layouts_state: "user",
                acfe_flexible_modal_edit: {
                  acfe_flexible_modal_edit_enabled: "0",
                  acfe_flexible_modal_edit_size: "large"
                },
                acfe_flexible_modal: {
                  acfe_flexible_modal_enabled: "1",
                  acfe_flexible_modal_title: "",
                  acfe_flexible_modal_size: "full",
                  acfe_flexible_modal_col: "4",
                  acfe_flexible_modal_categories: "0"
                }
            }
        ],
        location: [
            [
                {
                    param: "post_type",
                    operator: "==",
                    value: "page"
                }
            ]
        ],
        menu_order: 0,
        position: "normal",
        style: "default",
        label_placement: "top",
        instruction_placement: "label",
        hide_on_screen: [
            "the_content"
        ],
        active: true,
        description: "",
        show_in_rest: 1,
        acfe_display_title: "",
        acfe_autosync: ["json"],
        acfe_form: 0,
        acfe_meta: "",
        acfe_note: "",
        modified: Math.floor(Date.now() / 1000)
    };
}

function updatePageModulesJson(moduleFiles) {
    let pageModulesJson;

    if (fs.existsSync(pageModulesJsonPath)) {
        pageModulesJson = JSON.parse(fs.readFileSync(pageModulesJsonPath, 'utf8'));
    } else {
        pageModulesJson = createInitialPageModulesJson();
    }

    pageModulesJson.fields[0].layouts = moduleFiles.reduce((layouts, file, index) => {
        const moduleName = path.basename(file, '.php');
        const layoutKey = `layout_${generateConsistentKey(moduleName, 'page_modules')}`;

        layouts[layoutKey] = {
            key: layoutKey,
            name: `page_modules__${moduleName}`,
            label: moduleName.charAt(0).toUpperCase() + moduleName.slice(1).replace(/_/g, ' '),
            display: "block",
            sub_fields: [
                {
                    key: `field_${generateConsistentKey('clone', moduleName)}`,
                    label: "",
                    name: "",
                    type: "clone",
                    clone: [`group_module_${moduleName}`],
                    display: "seamless",
                    layout: "block",
                    prefix_label: 0,
                    prefix_name: 0
                }
            ],
            min: "",
            max: ""
        };

        return layouts;
    }, {});

    fs.writeFileSync(pageModulesJsonPath, JSON.stringify(pageModulesJson, null, 2));
    console.log('Updated page modules JSON:', pageModulesJsonPath);
}

function updateModuleAcfJson(moduleFiles) {
    moduleFiles.forEach(file => {
        const moduleName = path.basename(file, '.php');
        const jsonFilePath = path.join(acfJsonPath, `group_module_${moduleName}.json`);
        const phpFilePath = path.join(templatePartsPath, file);

        let acfJson = fs.existsSync(jsonFilePath)
            ? JSON.parse(fs.readFileSync(jsonFilePath, 'utf8'))
            : {
                key: `group_module_${moduleName}`,
                title: `Module: ${moduleName.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}`,
                fields: [],
                location: [[{ param: "post_type", operator: "==", value: "post" }]],
                menu_order: 0,
                position: "normal",
                style: "default",
                label_placement: "left",
                instruction_placement: "label",
                hide_on_screen: "",
                active: false,
                description: "",
                show_in_rest: 1,
                acfe_display_title: "",
                acfe_autosync: ["json"],
                acfe_form: 0,
                acfe_meta: "",
                acfe_note: "",
                modified: Math.floor(Date.now() / 1000)
            };

        const fieldDefinitions = parseFieldDefinitions(phpFilePath);
        const existingFieldsMap = new Map(acfJson.fields.map(field => [field.name, field]));

        acfJson.fields = organizeFields(fieldDefinitions, moduleName, existingFieldsMap);

        fs.writeFileSync(jsonFilePath, JSON.stringify(acfJson, null, 2));
    });
}

function updateAcfJson() {
    const moduleFiles = getModuleFiles();
    updatePageModulesJson(moduleFiles);
    updateModuleAcfJson(moduleFiles);
    generatePhpFilters(moduleFiles);
    console.log('ACF JSON files and PHP filters updated successfully.');
}

updateAcfJson();