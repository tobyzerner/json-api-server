<?php

namespace Tobyz\JsonApiServer\Translation;

final class EnglishCatalogue
{
    public static function messages(): array
    {
        return [
            'laravel.filter.count_single_value' => 'Count filters require a single value',
            'laravel.filter.unsupported_operator' => 'Unsupported operator: :operator',

            'filter.structure_invalid' => 'Filter structure is invalid',
            'filter.invalid' => 'Invalid filter: :filter',

            'pagination.size_invalid' => 'Page size must be a positive integer',
            'pagination.cursor_invalid' => 'Page cursor invalid',
            'pagination.offset_invalid' => 'Page offset must be a non-negative integer',
            'pagination.size_exceeded' => 'Page size requested is too large',
            'pagination.range_not_supported' => 'Range pagination is not supported',

            'request.query_parameter_invalid' => 'Invalid query parameter: :parameter',
            'request.sort_invalid' => 'Invalid sort: :sort',
            'request.filter_invalid' => 'Filter parameter must be an array',
            'request.include_invalid' => 'Invalid include: :include',
            'request.fields_invalid' => 'Sparse fieldsets must be comma-separated strings',

            'atomic.operations_invalid' =>
                'atomic:operations must be an array of operation objects',
            'atomic.href_ref_exclusive' => 'Only one of href and ref may be specified',
            'atomic.operation_invalid' => 'Invalid operation: :operation',
            'atomic.ref_unsupported' => 'ref is not supported for this operation',

            'relationship.invalid' => 'Invalid relationship object',
            'relationship.data_invalid' => 'Relationship data must be a list of identifier objects',

            'data.invalid' => 'Invalid data object',
            'data.type_invalid' => 'Invalid type value',
            'data.type_unsupported' => 'Type not allowed',
            'data.id_invalid' => 'Invalid ID value',
            'data.id_conflict' => 'ID does not match the resource ID',
            'data.attributes_invalid' => 'Invalid attributes object',
            'data.relationships_invalid' => 'Invalid relationships object',
            'data.field_unknown' => 'Unknown field: :field',
            'data.field_readonly' => 'Field is not writable',
            'data.field_required' => 'Field is required',

            'resource.not_found' => 'Resource not found: :identifier',
        ];
    }
}
