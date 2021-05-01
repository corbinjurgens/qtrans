# Introduction

This package helps you access your key translations (json translations not supported) with some handy features, and allows you to modify how the translation is displayed by chaining 'processors'

## Before
In cases where your translations in a certain area are all pointing to the same place, its not very DRY to do this

```html
{{ __('columns.users.name') }}
{{ __('columns.users.email') }}
{!! __('columns.users.description') !!}
```

## After
Instead of __() or trans(), use ___() or qtrans()
Use "shift" function to declare that all following translations should look in a certain area

You can use qtrans($key) or qtrans()->get($key), the latter useful for when you want to call other functions

**Inline shift**
```html
{{ qtrans()->shift('columns.users')->get('name') }}
{{ qtrans('email') }}
{{ qtrans('description') }}
```

**Pre-shift**
```html
@php(qtrans()->shift('columns.users'))

{{ qtrans('name') }}
{{ qtrans('email') }}
{{ qtrans('description') }}
```

## Warning

QTrans by default escapes translation strings. You can edit this in config qtrans.processor. The intention is that you will not escape translations as generally they should be safe, so I recommend turning off escape default. However if you manually apply HTML processors to a translation and forget escape(), it will not be escaped, even if escape is in the defaults. 

# Installation
## Composer

`composer require corbinjurgens/qtrans`

## Manual Installation

Copy the following to main composer.(in the case that the package is added to packages/corbinjurgens/qroute)
```
 "autoload": {
	"psr-4": {
		"Corbinjurgens\\QTrans\\": "packages/corbinjurgens/qtrans/src"
	},
	"files": {
		"packages/corbinjurgens/qtrans/src/helpers.php"
	}
},
```
and run 
```
composer dump-autoload
```


Add the following to config/app.php providers
```
Corbinjurgens\QTrans\ServiceProvider::class,
```
Add alias to config/app.php alias
```
"QTrans" => Corbinjurgens\Trans\Facade::class,
```

# Usage

See introduction for basic usage

## Multiple Options

When shifting, you can pass an array to declare that the translation key can be found in one of muliple places

In the following example, each key eg. 'name' will look to 'forms.signup.name', then if not found 'columns.users.name' to find the translation.

This is my preference for arranging table column translations, have general translations in one area, then translations specific to the current page in another area with only translations given that differ from the general.

```html
@php(qtrans()->shift(['forms.signup', 'columns.users']))

{{ qtrans('name') }}
{{ qtrans('email') }}
{{ qtrans('description') }}
```

## Give a preference dynamically

Your translations may have a set list of places to be found, and the number 1 preference may depend on a variable. In such case use priority function.
The given variable should be one that exisst in the given shift array, but it can also be one not in the array.

```php
$get_page = request()->query('page', 'columns.users');
$shifts = [
	'signup' => 'forms.signup',
	'manage' => 'columns.users',
];
$page = $shifts[$get_page] ?? 'columns.users';
```

```html
@php(qtrans()->shift(['forms.signup', 'columns.users'])->priority($page))

{{ qtrans('name') }}
{{ qtrans('email') }}
{{ qtrans('description') }}
```

## Shifting elsewhere, then back

When shifting a second, third or futher times, the history of you shifts is kept, and you can back out to the previous shift with back()

```html
@php(qtrans()->shift(['forms.signup', 'columns.users']))

{{ qtrans('name') }} {{-- forms.signup.name, columns.users.name --}}
{{ qtrans('email') }} {{-- forms.signup.email, columns.users.email --}}
{{ qtrans('description') }} {{-- forms.signup.description, columns.users.description --}}

@php(qtrans()->shift('common'))

{{ qtrans('header') }} {{-- common.header --}}
{{ qtrans('url') }} {{-- common.url --}}

@php(qtrans()->back())

{{ qtrans('address') }} {{-- forms.signup.address, columns.users.address --}}
```

## Deeper and deeper

When shifting, if you pass true as a second variable, the given shift value or values will be an extension of the previous shift.
Only possible after the 1st shift

```html
@php(qtrans()->shift(['forms.signup', 'columns.users']))

{{ qtrans('name') }} {{-- forms.signup.name, columns.users.name --}}
{{ qtrans('email') }} {{-- forms.signup.email, columns.users.email --}}
{{ qtrans('description') }} {{-- forms.signup.description, columns.users.description --}}

@php(qtrans()->shift('common', true))

{{ qtrans('header') }} {{-- forms.signup.common.header, columns.users.common.header --}}
{{ qtrans('url') }} {{-- forms.signup.common.url, columns.users.common.header --}}

@php(qtrans()->back())

{{ qtrans('address') }} {{-- forms.signup.address, columns.users.address --}}
```

Of course you can give array each shift and it'll follow the following pattern when searching for translation. Actual use cases for this seem rare though.
```html
@php(qtrans()->shift(['forms.signup', 'columns.users']))
@php(qtrans()->shift(['common', 'address'], true))
@php(qtrans()->shift(['contact', 'private_contact'], true))

{{ qtrans('cell') }}

```


The key "cell" will be searched for in
```
[
  0 => "forms.signup.common.contact"
  1 => "forms.signup.common.private_contact"
  2 => "forms.signup.address.contact"
  3 => "forms.signup.address.private_contact"
  4 => "columns.users.common.contact"
  5 => "columns.users.common.private_contact"
  6 => "columns.users.address.contact"
  7 => "columns.users.address.private_contact"
]
```
Use current() function to get an array like above showing the current shift levels bases

## Reset

To clear shift history and start a new base, or start an empty base use base()

## Temporary pause shift

Use pause() then later resume() to turn off shift features temporary and look for key raw as with normal translations
```html
@php(qtrans()->shift(['forms.signup', 'columns.users']))

@php(qtrans()->pause())
{{ qtrans('user.cell') }} {{ user.cell }}

@php(qtrans()->resume())
{{ qtrans('user.cell') }} {{ forms.signup.user.cell, columns.users.user.cell }}

```


Or use skip() to pause shift feature for the next translation. You can pass a numer to skip if you want to skip a certain numer of times

```html
@php(qtrans()->shift(['forms.signup', 'columns.users']))

@php(qtrans()->skip())
{{ qtrans('user.cell') }} {{ user.cell }}

{{ qtrans('user.cell') }} {{ forms.signup.user.cell, columns.users.user.cell }}

```

## Html processors

If the current instance $basic_setting property is set to false (determined by config 'qtrans.basic'), all returned translation values will be an 'Htmlable' instance.
This allows you to chain processors.

**Before**

If you wanted to use the nl2br function and escape, you needed to do something like
```html
{!! nl2br( e( __('translation.key') ) ) !!}
```
Which is very messy.

**After**

```html
{{ ___('translation.key')->escape()->br() }}
```

Note the order of processors matters. Running br()->escape() will cause html <br /> tags to show as plain text

**Available Processors**
- escape(): apply e() to string
- br(): apply nl2br() to string
- markdown(): apply Illuminate\Mail\Markdown::parse() to string, which is laravel's built in mail markdown, also useful for html markdown

### Html processor defaults

To apply a set of default processors to all your translations, modify and add to the config qtrans.processor array. 

For example, by default processors is set as
```php
'processor' => [
	['escape', [] ], 
]
```
This means for the situation `{{ ___('users.name') }}`, the escape processor is applied. BUT if you manually call a processor such as `{{ ___('users.name')->br() }}` escape, or any other processors declared in default wont be used. This is for situations where you want to reorder the processors.

If you dont want to use the default processors for a specific translation but don't have any processors to replace it with call clear() for example `{{ ___('users.name')->clear() }}`. You may also modify the default processors by editing $processor_setting array property eg `qtrans()->processor_setting = []`

### Custom Processor

Create a custom processor by calling Corbinjurgens\QTrans\Html::custom()

For example, in your AppServiceProvider register function

```php
Corbinjurgens\QTrans\Html::custom('upper', function($string){
	return \Str::upper($string)
});
```

```html
{{ ___('users.name')->escape()->upper() }}
```