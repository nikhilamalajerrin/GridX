<?php

namespace GridX\Storefront\Http\Filter;

use GridX\Http\Filter\Filter;

class NotificationChannelFilter extends Filter
{
    public function queryForInternal()
    {
        $this->builder->where('company_uuid', $this->session->get('company'));
    }
}
