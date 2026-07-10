const lowercase = (string) => string.toLowerCase();

export default function serializeNormalizePolymorphicType(polymorphicTypeValue, defaultValue = null) {
    // encode first
    const encodedPolymorphicTypeValue = encodeURI(polymorphicTypeValue);
    // if encoded is invalid send the default value or the original value back
    if (!encodedPolymorphicTypeValue.includes('%5C')) {
        return defaultValue || polymorphicTypeValue;
    }
    // from gridx api should be something like GridX\Models\Model
    // change value to ember-data polymorphic type
    return lowercase(encodedPolymorphicTypeValue.split('%5C').reverse()[0]);
}
