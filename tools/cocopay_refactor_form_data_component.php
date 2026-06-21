<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
$componentDir = $root . '/app/View/Components';
$viewsDir = $root . '/resources/views';
$controllersDir = $root . '/app/Http/Controllers';

$formDataClass = <<<'PHP'
<?php

namespace App\View\Components;

use App\Models\Form;
use Illuminate\View\Component;

class FormData extends Component {
    public $identifier;
    public $identifierValue;
    public $form;
    public $formData;
    public $title;
    public $builderMode;

    public function __construct($identifier = null, $identifierValue = null, $form = null, $title = 'User Data') {
        $this->identifier = $identifier;
        $this->identifierValue = $identifierValue;
        $this->title = $title;
        $this->builderMode = $identifier === null;

        if ($this->builderMode) {
            $this->form = $form;
            $this->formData = [];
            return;
        }

        $this->form = Form::where($this->identifier, $this->identifierValue)->first();
        $this->formData = @$this->form->form_data ?? [];
    }

    public function render() {
        return view($this->builderMode ? 'components.form-data-builder' : 'components.form-data');
    }
}
PHP;

$viserCompatibilityClass = <<<'PHP'
<?php

namespace App\View\Components;

class ViserForm extends FormData {
}
PHP;

$nFormCompatibilityClass = <<<'PHP'
<?php

namespace App\View\Components;

class nForm extends FormData {
}
PHP;

$formDataView = <<<'BLADE'
@if ($formData)
    @foreach ($formData as $data)
        <div class="form-group">
            <label class="form-label @if ($data->is_required == 'required') required @endif">{{ __(keyToTitle($data->name)) }}</label>
            @if ($data->type == 'text')
                <input type="text" class="form-control form--control" name="{{ $data->label }}" value="{{ old($data->label) }}" @if ($data->is_required == 'required') required @endif>
            @elseif($data->type == 'textarea')
                <textarea class="form-control form--control" name="{{ $data->label }}" @if ($data->is_required == 'required') required @endif>{{ old($data->label) }}</textarea>
            @elseif($data->type == 'select')
                <select class="form-control form--control" name="{{ $data->label }}" @if ($data->is_required == 'required') required @endif>
                    <option value="">@lang('Select One')</option>
                    @foreach ($data->options as $item)
                        <option value="{{ $item }}" @selected($item == old($data->label))>{{ __($item) }}</option>
                    @endforeach
                </select>
            @elseif($data->type == 'checkbox')
                @foreach ($data->options as $option)
                    <div class="form-check">
                        <input class="form-check-input" name="{{ $data->label }}[]" type="checkbox" value="{{ $option }}" id="{{ $data->label }}_{{ titleToKey($option) }}">
                        <label class="form-check-label" for="{{ $data->label }}_{{ titleToKey($option) }}">{{ $option }}</label>
                    </div>
                @endforeach
            @elseif($data->type == 'radio')
                @foreach ($data->options as $option)
                    <div class="form-check">
                        <input class="form-check-input" name="{{ $data->label }}" type="radio" value="{{ $option }}" id="{{ $data->label }}_{{ titleToKey($option) }}" @checked($option == old($data->label))>
                        <label class="form-check-label" for="{{ $data->label }}_{{ titleToKey($option) }}">{{ $option }}</label>
                    </div>
                @endforeach
            @elseif($data->type == 'file')
                <input type="file" class="form-control form--control" name="{{ $data->label }}" @if ($data->is_required == 'required') required @endif accept="@foreach (explode(',', $data->extensions) as $ext) .{{ $ext }}, @endforeach">
                <pre class="text--base mt-1">@lang('Supported mimes'): {{ $data->extensions }}</pre>
            @endif
        </div>
    @endforeach
@endif
BLADE;

$formDataBuilderView = <<<'BLADE'
@props([
    'title' => $title ?? 'User Data',
    'form' => $form ?? null,
])

<div class="card mt-3">

    <div class="card-header d-flex justify-content-between">
        <h5 class="card-title">{{ __(@$title) }}</h5>
        <button type="button" class="btn btn-sm btn-outline--primary float-end form-generate-btn">
            <i class="la la-fw la-plus"></i>@lang('Add New')
        </button>
    </div>

    <div class="card-body">
        <div class="row addedField">
            @if (@$form)
                @foreach ($form->form_data as $formData)
                    <div class="col-md-4">
                        <div class="card border mb-3" id="{{ $loop->index }}">
                            <input type="hidden" name="form_generator[is_required][]" value="{{ $formData->is_required }}">
                            <input type="hidden" name="form_generator[extensions][]" value="{{ $formData->extensions }}">
                            <input type="hidden" name="form_generator[options][]" value="{{ implode(',', $formData->options) }}">
                            <div class="card-body">
                                <div class="form-group">
                                    <label>@lang('Label')</label>
                                    <input type="text" name="form_generator[form_label][]" class="form-control" value="{{ $formData->name }}" readonly>
                                </div>
                                <div class="form-group">
                                    <label>@lang('Type')</label>
                                    <input type="text" name="form_generator[form_type][]" class="form-control" value="{{ $formData->type }}" readonly>
                                </div>
                                @php
                                    $jsonData = getFormData($formData);
                                @endphp
                                @if (!@$formData->default)
                                    <div class="btn-group w-100">
                                        <button type="button" class="btn btn--primary editFormData" data-form_item="{{ $jsonData }}" data-update_id="{{ $loop->index }}">
                                            <i class="las la-pen"></i>
                                        </button>
                                        <button type="button" class="btn btn--danger removeFormData">
                                            <i class="las la-times"></i>
                                        </button>
                                    </div>
                                @else
                                    <div class="bg--danger w-100 p-1 rounded text-center"> @lang('Must be Required')</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

@push('script')
    <script>
        "use strict"
        var formGenerator = new FormGenerator();
        formGenerator.totalField = {{ @$form ? count((array) $form->form_data) : 0 }}
    </script>

    <script src="{{ asset('assets/global/js/form_actions.js') }}"></script>
@endpush
BLADE;

foreach ([$componentDir, $viewsDir, $controllersDir] as $dir) {
    if (!is_dir($dir)) {
        throw new RuntimeException("Missing directory: {$dir}");
    }
}

file_put_contents($componentDir . '/FormData.php', $formDataClass);
file_put_contents($componentDir . '/ViserForm.php', $viserCompatibilityClass);
file_put_contents($componentDir . '/nForm.php', $nFormCompatibilityClass);
file_put_contents($viewsDir . '/components/form-data.blade.php', $formDataView);
file_put_contents($viewsDir . '/components/form-data-builder.blade.php', $formDataBuilderView);

$aliasView = "@include('components.form-data')\n";
file_put_contents($viewsDir . '/components/viser-form.blade.php', $aliasView);
file_put_contents($viewsDir . '/components/n-form.blade.php', $aliasView);

$builderAliasView = "@include('components.form-data-builder')\n";
file_put_contents($viewsDir . '/components/viser-form-data.blade.php', $builderAliasView);
file_put_contents($viewsDir . '/components/n-form-data.blade.php', $builderAliasView);

$replacements = [
    '<x-viser-form-data' => '<x-form-data',
    '</x-viser-form-data>' => '</x-form-data>',
    '<x-viser-form' => '<x-form-data',
    '</x-viser-form>' => '</x-form-data>',
    "view('components.viser-form'" => "view('components.form-data'",
    'view("components.viser-form"' => 'view("components.form-data"',
    "view('components.n-form'" => "view('components.form-data'",
    'view("components.n-form"' => 'view("components.form-data"',
];

$scanDirs = [$viewsDir, $controllersDir];
$changed = [];
foreach ($scanDirs as $scanDir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (!preg_match('/\.(php|blade\.php)$/', $path)) {
            continue;
        }
        $contents = file_get_contents($path);
        $updated = str_replace(array_keys($replacements), array_values($replacements), $contents);
        if ($updated !== $contents) {
            file_put_contents($path, $updated);
            $changed[] = $path;
        }
    }
}

echo "FormData component refactor complete.\n";
echo "Changed files: " . count($changed) . "\n";
foreach ($changed as $path) {
    echo " - {$path}\n";
}
