const fs = require('fs');
const path = require('path');
const inquirer = require('inquirer');

const templatePartsPath = './develop/template-parts/page-modules';

function getPageComponents() {
    return fs.readdirSync(templatePartsPath)
        .filter(file => path.extname(file).toLowerCase() === '.php')
        .map(file => path.basename(file, '.php'));
}

async function promptUser(components) {
    const answers = await inquirer.prompt([
        {
            type: 'list',
            name: 'component',
            message: 'Select a component to edit:',
            choices: [...components, new inquirer.Separator(), 'Quit'],
            pageSize: 20
        }
    ]);

    return answers.component;
}

function getExistingFields(componentPath) {
    const content = fs.readFileSync(componentPath, 'utf8');
    const match = content.match(/<!--\s*(\[[\s\S]*?\])\s*-->/);
    if (match) {
        return JSON.parse(match[1]);
    }
    return [];
}

async function editFields(componentPath) {
    let fields = getExistingFields(componentPath);
    
    while (true) {
        const { action } = await inquirer.prompt([
            {
                type: 'list',
                name: 'action',
                message: 'What would you like to do?',
                choices: ['Add new field', 'Edit existing field', 'Delete field', 'Finish editing']
            }
        ]);

        if (action === 'Finish editing') break;

        switch (action) {
            case 'Add new field':
                fields.push(await promptForFieldDetails({}, fields));
                break;
            case 'Edit existing field':
                await editExistingField(fields);
                break;
            case 'Delete field':
                await deleteField(fields);
                break;
        }
    }

    return fields;
}

async function promptForFieldDetails(existingField = {}, existingFields = []) {
    const acfFieldTypes = [
        'text', 'textarea', 'number', 'range', 'email', 'url', 'password',
        'wysiwyg', 'oembed', 'image', 'file', 'gallery', 'select', 'checkbox',
        'radio', 'button_group', 'true_false', 'link', 'post_object', 'page_link',
        'relationship', 'taxonomy', 'user', 'google_map', 'date_picker', 'date_time_picker',
        'time_picker', 'color_picker', 'message', 'accordion', 'tab', 'group', 'repeater', 'flexible_content'
    ];

    const questions = [
        {
            type: 'input',
            name: 'fieldName',
            message: 'Enter the field name:',
            default: existingField.fieldName || ''
        },
        {
            type: 'input',
            name: 'fieldLabel',
            message: 'Enter the field label:',
            default: existingField.fieldLabel || ''
        },
        {
            type: 'list',
            name: 'fieldType',
            message: 'Select the field type:',
            choices: acfFieldTypes,
            default: existingField.fieldType || 'text'
        },
        {
            type: 'confirm',
            name: 'required',
            message: 'Is this field required?',
            default: existingField.required || false
        },
        {
            type: 'input',
            name: 'defaultValue',
            message: 'Enter the default value (if any):',
            default: existingField.defaultValue || ''
        },
        {
            type: 'list',
            name: 'parent',
            message: 'Select a parent field (or none for top-level):',
            choices: [
                { name: 'None (top-level field)', value: null },
                ...existingFields
                    .filter(field => ['repeater', 'group', 'flexible_content'].includes(field.fieldType))
                    .map(field => ({ name: field.fieldName, value: field.fieldName }))
            ],
            default: existingField.parent || null
        }
    ];

    const answers = await inquirer.prompt(questions);

    if (answers.fieldType === 'select' || answers.fieldType === 'checkbox' || answers.fieldType === 'radio') {
        const { choices } = await inquirer.prompt([
            {
                type: 'input',
                name: 'choices',
                message: 'Enter choices (comma-separated key:value pairs, e.g. red:Red,blue:Blue):',
                filter: input => {
                    const pairs = input.split(',');
                    return pairs.reduce((obj, pair) => {
                        const [key, value] = pair.split(':');
                        obj[key.trim()] = value.trim();
                        return obj;
                    }, {});
                }
            }
        ]);
        answers.choices = choices;
    }

    return answers;
}

async function editExistingField(fields) {
    const { fieldToEdit } = await inquirer.prompt([
        {
            type: 'list',
            name: 'fieldToEdit',
            message: 'Select a field to edit:',
            choices: fields.map(field => field.fieldName)
        }
    ]);

    const fieldIndex = fields.findIndex(field => field.fieldName === fieldToEdit);
    const updatedField = await promptForFieldDetails(fields[fieldIndex], fields);
    fields[fieldIndex] = updatedField;
}

async function deleteField(fields) {
    const { fieldToDelete } = await inquirer.prompt([
        {
            type: 'list',
            name: 'fieldToDelete',
            message: 'Select a field to delete:',
            choices: fields.map(field => field.fieldName)
        }
    ]);

    const fieldIndex = fields.findIndex(field => field.fieldName === fieldToDelete);
    fields.splice(fieldIndex, 1);
}

function updateComponentFile(componentPath, fields) {
    let content = fs.readFileSync(componentPath, 'utf8');
    const updatedComment = `<!-- ${JSON.stringify(fields, null, 2)} -->`;
    
    if (content.includes('<!--') && content.includes('-->')) {
        content = content.replace(/<!--[\s\S]*?-->/, updatedComment);
    } else {
        content = updatedComment + '\n\n' + content;
    }

    fs.writeFileSync(componentPath, content);
}

async function main() {
    const components = getPageComponents();
    
    while (true) {
        const selectedComponent = await promptUser(components);
        
        if (selectedComponent === 'Quit') {
            console.log('Goodbye!');
            break;
        }
        
        const componentPath = path.join(templatePartsPath, `${selectedComponent}.php`);
        const updatedFields = await editFields(componentPath);
        updateComponentFile(componentPath, updatedFields);
        console.log(`Updated ${selectedComponent} successfully.`);
        
        const { continueEditing } = await inquirer.prompt([
            {
                type: 'confirm',
                name: 'continueEditing',
                message: 'Do you want to edit another component?',
                default: true
            }
        ]);
        
        if (!continueEditing) {
            console.log('Goodbye!');
            break;
        }
    }
}

main().catch(error => console.error('An error occurred:', error));