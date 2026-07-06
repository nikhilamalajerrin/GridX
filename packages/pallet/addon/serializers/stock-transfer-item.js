import ApplicationSerializer from '@gridx/ember-core/serializers/application';
import { EmbeddedRecordsMixin } from '@ember-data/serializer/rest';

export default class StockTransferItemSerializer extends ApplicationSerializer.extend(EmbeddedRecordsMixin) {
    get attrs() {
        return {
            product: { embedded: 'always' },
            variant: { embedded: 'always' },
        };
    }
}
