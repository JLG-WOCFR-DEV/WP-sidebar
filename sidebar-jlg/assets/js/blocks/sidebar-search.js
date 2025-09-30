import metadata from '../../blocks/sidebar-search/block.json';
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
    PanelBody,
    SelectControl,
    TextareaControl,
    ToggleControl,
    __experimentalVStack as VStack,
    Notice,
} from '@wordpress/components';
import { useEffect, useMemo, useRef } from '@wordpress/element';

const METHOD_OPTIONS = [
    { value: 'default', label: __('Formulaire de recherche WordPress', 'sidebar-jlg') },
    { value: 'shortcode', label: __('Shortcode personnalisé', 'sidebar-jlg') },
    { value: 'hook', label: __('Hook "jlg_sidebar_search_area"', 'sidebar-jlg') },
];

const ALIGNMENT_OPTIONS = [
    { value: 'flex-start', label: __('Alignement à gauche', 'sidebar-jlg') },
    { value: 'center', label: __('Alignement centré', 'sidebar-jlg') },
    { value: 'flex-end', label: __('Alignement à droite', 'sidebar-jlg') },
];

const localizedDefaults = window.SidebarJlgSearchBlock?.defaults ?? {};
const blockName = window.SidebarJlgSearchBlock?.blockName ?? metadata.name;

registerBlockType(metadata, {
    edit({ attributes, setAttributes, clientId }) {
        const initRef = useRef(false);

        useEffect(() => {
            if (initRef.current) {
                return;
            }

            initRef.current = true;

            const initialValues = {};
            ['enable_search', 'search_method', 'search_alignment', 'search_shortcode'].forEach((key) => {
                if (attributes[key] === undefined && localizedDefaults[key] !== undefined) {
                    initialValues[key] = localizedDefaults[key];
                }
            });

            if (Object.keys(initialValues).length > 0) {
                setAttributes(initialValues);
            }
        }, [attributes, setAttributes]);

        const normalizedAttributes = useMemo(() => ({
            enable_search: attributes.enable_search ?? false,
            search_method: attributes.search_method ?? 'default',
            search_alignment: attributes.search_alignment ?? 'flex-start',
            search_shortcode: attributes.search_shortcode ?? '',
        }), [attributes.enable_search, attributes.search_method, attributes.search_alignment, attributes.search_shortcode]);

        const blockProps = useBlockProps({
            className: 'sidebar-jlg-search-block',
        });

        const alignmentClass = useMemo(() => {
            switch (normalizedAttributes.search_alignment) {
                case 'center':
                    return 'sidebar-search--align-center';
                case 'flex-end':
                    return 'sidebar-search--align-end';
                default:
                    return 'sidebar-search--align-start';
            }
        }, [normalizedAttributes.search_alignment]);

        const renderPreviewContent = () => {
            if (!normalizedAttributes.enable_search) {
                return (
                    <Notice status="info" isDismissible={ false }>
                        { __('La recherche est désactivée pour cette instance.', 'sidebar-jlg') }
                    </Notice>
                );
            }

            if (normalizedAttributes.search_method === 'shortcode') {
                if (!normalizedAttributes.search_shortcode) {
                    return (
                        <div className="sidebar-search__placeholder">
                            { __('Ajoutez un shortcode valide pour afficher un aperçu.', 'sidebar-jlg') }
                        </div>
                    );
                }

                return (
                    <code className="sidebar-search__shortcode-preview">
                        { normalizedAttributes.search_shortcode }
                    </code>
                );
            }

            if (normalizedAttributes.search_method === 'hook') {
                return (
                    <div className="sidebar-search__placeholder">
                        { __('Le contenu sera injecté via le hook "jlg_sidebar_search_area".', 'sidebar-jlg') }
                    </div>
                );
            }

            return (
                <form className="sidebar-search__preview-form" aria-label={ __('Formulaire de recherche', 'sidebar-jlg') }>
                    <label htmlFor={`sidebar-search-input-${ clientId }`} className="screen-reader-text">
                        { __('Rechercher :', 'sidebar-jlg') }
                    </label>
                    <div className="sidebar-search__preview-fields">
                        <input
                            id={`sidebar-search-input-${ clientId }`}
                            type="search"
                            placeholder={ __('Rechercher…', 'sidebar-jlg') }
                            readOnly
                        />
                        <button type="submit" disabled>
                            { __('Recherche', 'sidebar-jlg') }
                        </button>
                    </div>
                </form>
            );
        };

        return (
            <>
                <InspectorControls>
                    <PanelBody title={ __('Options de recherche', 'sidebar-jlg') } initialOpen>
                        <VStack spacing={ 3 }>
                            <ToggleControl
                                label={ __('Activer la recherche', 'sidebar-jlg') }
                                checked={ !!normalizedAttributes.enable_search }
                                onChange={(value) => setAttributes({ enable_search: value })}
                            />
                            <SelectControl
                                label={ __('Méthode de recherche', 'sidebar-jlg') }
                                value={ normalizedAttributes.search_method }
                                options={ METHOD_OPTIONS }
                                onChange={(value) => setAttributes({ search_method: value }) }
                            />
                            <SelectControl
                                label={ __('Alignement', 'sidebar-jlg') }
                                value={ normalizedAttributes.search_alignment }
                                options={ ALIGNMENT_OPTIONS }
                                onChange={(value) => setAttributes({ search_alignment: value }) }
                            />
                            { normalizedAttributes.search_method === 'shortcode' && (
                                <TextareaControl
                                    label={ __('Shortcode', 'sidebar-jlg') }
                                    help={ __('Le shortcode est exécuté côté front et doit retourner un formulaire de recherche.', 'sidebar-jlg') }
                                    value={ normalizedAttributes.search_shortcode }
                                    onChange={(value) => setAttributes({ search_shortcode: value }) }
                                />
                            ) }
                        </VStack>
                    </PanelBody>
                </InspectorControls>
                <div { ...blockProps } data-sidebar-search-block-name={ blockName }>
                    <div
                        className={ `sidebar-search sidebar-search--block ${ alignmentClass }` }
                        data-sidebar-search-align={ normalizedAttributes.search_alignment }
                        style={ { justifyContent: normalizedAttributes.search_alignment } }
                    >
                        <div
                            className="sidebar-search__inner"
                            style={ {
                                display: 'flex',
                                width: '100%',
                                justifyContent: normalizedAttributes.search_alignment,
                            } }
                        >
                            { renderPreviewContent() }
                        </div>
                    </div>
                </div>
            </>
        );
    },
    save() {
        return null;
    },
});
