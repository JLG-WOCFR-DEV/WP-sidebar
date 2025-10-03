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
import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import '../../css/sidebar-search-editor.scss';

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

const COLOR_SCHEME_OPTIONS = [
    { value: 'auto', label: __('Palette automatique', 'sidebar-jlg') },
    { value: 'light', label: __('Palette claire', 'sidebar-jlg') },
    { value: 'dark', label: __('Palette sombre', 'sidebar-jlg') },
];

const isBrowserEnvironment = () => typeof window !== 'undefined' && typeof document !== 'undefined';

const parseRgbColor = (color) => {
    if (typeof color !== 'string') {
        return null;
    }

    if (color === '' || color.toLowerCase() === 'transparent') {
        return null;
    }

    const match = color.match(/^rgba?\(([^)]+)\)$/i);
    if (!match) {
        return null;
    }

    const parts = match[1]
        .split(',')
        .map((value) => parseFloat(value.trim()))
        .filter((value) => !Number.isNaN(value));

    if (parts.length < 3) {
        return null;
    }

    const [r, g, b, a = 1] = parts;

    return {
        r: Math.min(Math.max(r, 0), 255),
        g: Math.min(Math.max(g, 0), 255),
        b: Math.min(Math.max(b, 0), 255),
        a: Math.min(Math.max(a, 0), 1),
    };
};

const relativeLuminance = (color) => {
    const channel = (value) => {
        const normalized = value / 255;

        if (normalized <= 0.03928) {
            return normalized / 12.92;
        }

        return Math.pow((normalized + 0.055) / 1.055, 2.4);
    };

    return (
        0.2126 * channel(color.r) +
        0.7152 * channel(color.g) +
        0.0722 * channel(color.b)
    );
};

const isTransparentColor = (color) => !color || color.a <= 0.05;

const resolveAutoScheme = (element) => {
    if (!isBrowserEnvironment() || !element) {
        return 'dark';
    }

    let current = element;

    while (current) {
        if (current instanceof Element) {
            const style = window.getComputedStyle(current);
            const parsed = parseRgbColor(style.backgroundColor);

            if (!isTransparentColor(parsed)) {
                return relativeLuminance(parsed) < 0.5 ? 'dark' : 'light';
            }

            const parentNode = current.parentNode;
            if (parentNode instanceof ShadowRoot) {
                current = parentNode;
            } else if (current.parentElement) {
                current = current.parentElement;
            } else if (parentNode instanceof Document) {
                current = parentNode.documentElement;
            } else {
                current = parentNode;
            }

            continue;
        }

        if (current instanceof ShadowRoot) {
            current = current.host;
            continue;
        }

        if (current instanceof Document) {
            current = current.documentElement;
            continue;
        }

        break;
    }

    if (isBrowserEnvironment() && window.matchMedia) {
        try {
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
        } catch (error) {
            // Ignore media query errors (older browsers).
        }
    }

    return 'light';
};

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
            ['enable_search', 'search_method', 'search_alignment', 'search_color_scheme', 'search_shortcode'].forEach((key) => {
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
            search_color_scheme: attributes.search_color_scheme ?? 'auto',
            search_shortcode: attributes.search_shortcode ?? '',
        }), [
            attributes.enable_search,
            attributes.search_method,
            attributes.search_alignment,
            attributes.search_color_scheme,
            attributes.search_shortcode,
        ]);

        const blockProps = useBlockProps({
            className: 'sidebar-jlg-search-block',
        });

        const containerRef = useRef(null);
        const [resolvedScheme, setResolvedScheme] = useState(() => {
            switch (normalizedAttributes.search_color_scheme) {
                case 'light':
                    return 'light';
                case 'dark':
                    return 'dark';
                default:
                    return 'dark';
            }
        });

        useEffect(() => {
            if (normalizedAttributes.search_color_scheme === 'auto') {
                setResolvedScheme(resolveAutoScheme(containerRef.current));
                return;
            }

            setResolvedScheme(normalizedAttributes.search_color_scheme === 'light' ? 'light' : 'dark');
        }, [normalizedAttributes.search_color_scheme]);

        useEffect(() => {
            if (normalizedAttributes.search_color_scheme !== 'auto' || !isBrowserEnvironment()) {
                return undefined;
            }

            const element = containerRef.current;
            if (!element) {
                return undefined;
            }

            const updateScheme = () => {
                setResolvedScheme(resolveAutoScheme(element));
            };

            updateScheme();

            const observers = [];

            if ('ResizeObserver' in window) {
                const resizeObserver = new ResizeObserver(updateScheme);
                resizeObserver.observe(element);
                observers.push(() => resizeObserver.disconnect());
            }

            if ('MutationObserver' in window) {
                const targets = [element];
                if (element.parentElement) {
                    targets.push(element.parentElement);
                }

                targets.forEach((target) => {
                    if (!target) {
                        return;
                    }

                    const mutationObserver = new MutationObserver(updateScheme);
                    mutationObserver.observe(target, {
                        attributes: true,
                        subtree: false,
                        attributeFilter: ['class', 'style', 'data-sidebar-search-scheme'],
                    });
                    observers.push(() => mutationObserver.disconnect());
                });
            }

            if (window.matchMedia) {
                try {
                    const mq = window.matchMedia('(prefers-color-scheme: dark)');
                    const listener = () => updateScheme();

                    if (mq.addEventListener) {
                        mq.addEventListener('change', listener);
                        observers.push(() => mq.removeEventListener('change', listener));
                    } else if (mq.addListener) {
                        mq.addListener(listener);
                        observers.push(() => mq.removeListener(listener));
                    }
                } catch (error) {
                    // Ignore matchMedia errors.
                }
            }

            return () => {
                observers.forEach((cleanup) => cleanup());
            };
        }, [normalizedAttributes.search_color_scheme]);

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
                <form className="search-form" role="search" aria-label={ __('Formulaire de recherche', 'sidebar-jlg') }>
                    <label htmlFor={`sidebar-search-input-${ clientId }`}>
                        <span className="screen-reader-text">{ __('Rechercher :', 'sidebar-jlg') }</span>
                        <input
                            id={`sidebar-search-input-${ clientId }`}
                            className="search-field"
                            type="search"
                            placeholder={ __('Rechercher…', 'sidebar-jlg') }
                            readOnly
                        />
                    </label>
                    <button type="submit" className="search-submit" disabled>
                        { __('Recherche', 'sidebar-jlg') }
                    </button>
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
                            <SelectControl
                                label={ __('Palette de contraste', 'sidebar-jlg') }
                                value={ normalizedAttributes.search_color_scheme }
                                options={ COLOR_SCHEME_OPTIONS }
                                onChange={(value) => setAttributes({ search_color_scheme: value }) }
                                help={ __('Permet de forcer des couleurs adaptées à un fond clair ou sombre. En mode automatique, la palette s’ajuste selon le contraste détecté.', 'sidebar-jlg') }
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
                        ref={ containerRef }
                        className={ `sidebar-search ${ alignmentClass } sidebar-search--scheme-${ resolvedScheme }` }
                        data-sidebar-search-align={ normalizedAttributes.search_alignment }
                        data-sidebar-search-scheme={ normalizedAttributes.search_color_scheme }
                        data-sidebar-search-applied-scheme={ resolvedScheme }
                        style={ { '--sidebar-search-alignment': normalizedAttributes.search_alignment } }
                    >
                        { renderPreviewContent() }
                    </div>
                </div>
            </>
        );
    },
    save() {
        return null;
    },
});
