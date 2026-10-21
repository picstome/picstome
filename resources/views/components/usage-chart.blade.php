@props(['series', 'label'])

<flux:chart :value="$series" class="aspect-[3/1]">
    <flux:chart.svg>
        <flux:chart.bar field="value" class="text-brand-green dark:text-brand-sage" />

        <flux:chart.axis axis="x" field="label" :format="['month' => 'short', 'year' => 'numeric']">
            <flux:chart.axis.tick />
            <flux:chart.axis.line />
        </flux:chart.axis>

        <flux:chart.axis axis="y" :format="['useGrouping' => true]">
            <flux:chart.axis.grid />
            <flux:chart.axis.tick />
        </flux:chart.axis>
    </flux:chart.svg>

    <flux:chart.tooltip>
        <flux:chart.tooltip.heading field="label" :format="['month' => 'short', 'year' => 'numeric']" />

        <flux:chart.tooltip.value field="value" :$label :format="['useGrouping' => true]" />
    </flux:chart.tooltip>
</flux:chart>
