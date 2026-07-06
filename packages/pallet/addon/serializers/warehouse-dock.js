import ApplicationSerializer from '@gridx/ember-core/serializers/application';
import { EmbeddedRecordsMixin } from '@ember-data/serializer/rest';

export default class WarehouseDockSerializer extends ApplicationSerializer.extend(EmbeddedRecordsMixin) {}
