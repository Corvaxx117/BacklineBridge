export function stageFamily(item, config, sources) {
    const linked = new Set(
        (item.patchSources ?? [])
            .map((key) => sources.get(key))
            .filter((source) => source?.available && source.family)
            .map((source) => source.family),
    );
    if (linked.size === 1) return [...linked][0];
    const text = `${item.label ?? ''} ${item.notes ?? ''}`.toLocaleLowerCase('fr');
    for (const rule of config.rules) {
        if (rule.kinds.includes(item.kind) && rule.words.some((word) => text.includes(word))) {
            return rule.family;
        }
    }
    return config.defaults[item.kind] ?? 'utility';
}
