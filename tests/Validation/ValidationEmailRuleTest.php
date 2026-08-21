<?php

use Voyager\MagicAliases\MagicAlias;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\Translation\ArrayLoader;
use Voyager\Translation\Translator;
use Voyager\Validation\Rule;
use Voyager\Validation\Rules\Email;
use Voyager\Validation\ValidationServiceProvider;
use Voyager\Validation\Validator;
use Voyager\Vessel\Vessel;

const EMAIL_ATTRIBUTE = 'my_email';
const EMAIL_ATTRIBUTE_REPLACED = 'my email';

function emailAssertValidationRules($rule, $values, $expectToPass, $expectedMessages = [], $customValidationMessage = null)
{
    $values = Arr::wrap($values);

    $translator = resolve('translator');

    foreach ($values as $value) {
        $v = new Validator(
            $translator,
            [EMAIL_ATTRIBUTE => $value],
            [EMAIL_ATTRIBUTE => is_object($rule) ? clone $rule : $rule],
            $customValidationMessage ? [EMAIL_ATTRIBUTE.'.email' => $customValidationMessage] : []
        );

        expect($v->passes())->toBe($expectToPass);

        expect($v->messages()->toArray())->toBe(
            $expectToPass ? [] : [EMAIL_ATTRIBUTE => $expectedMessages]
        );
    }
}

function emailFails($rule, $values, $expectedMessages, $customValidationMessage = null)
{
    emailAssertValidationRules($rule, $values, false, $expectedMessages, $customValidationMessage);
}

function emailPasses($rule, $values)
{
    emailAssertValidationRules($rule, $values, true);
}

beforeEach(function () {
    $container = Vessel::getInstance();

    $container->bind('translator', function () {
        $translator = new Translator(
            new ArrayLoader, 'en'
        );

        $translator->addLines([
            'validation.email' => 'The :attribute must be a valid email address.',
        ], 'en');

        return $translator;
    });

    MagicAlias::setMagicAliasApplication($container);

    (new ValidationServiceProvider($container))->register();
});

afterEach(function () {
    Vessel::setInstance(null);

    MagicAlias::clearResolvedInstances();

    MagicAlias::setMagicAliasApplication(null);
});

test('basic', function () {
        emailFails(
            Email::default(),
            'foo',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email(),
            'foo',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Email::default(),
            12345,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email(),
            12345,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            Email::default(),
            'taylor@laravel.com'
        );

        emailPasses(
            Rule::email(),
            'taylor@laravel.com'
        );

        emailPasses(
            Rule::email(),
            ['taylor@laravel.com'],
        );

        emailPasses(
            Email::default(),
            ['taylor@laravel.com'],
        );

        emailPasses(Email::default(), null);

        emailPasses(Rule::email(), null);
    });

test('rfc compliant strict', function () {
        $emailThatFailsBothNonStrictButFailsInStrict = 'username@sub..example.com';
        $emailThatPassesNonStrictButFailsInStrict = '"has space"@example.com';
        $emailThatPassesBothNonStrictAndInStrict = 'plainaddress@example.com';

        emailFails(
            (new Email())->rfcCompliant(strict: true),
            $emailThatPassesNonStrictButFailsInStrict,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(strict: true),
            $emailThatPassesNonStrictButFailsInStrict,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            (new Email())->rfcCompliant(strict: true),
            $emailThatFailsBothNonStrictButFailsInStrict,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(strict: true),
            $emailThatFailsBothNonStrictButFailsInStrict,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            (new Email())->rfcCompliant(strict: true),
            $emailThatPassesBothNonStrictAndInStrict
        );

        emailPasses(
            Rule::email()->rfcCompliant(strict: true),
            $emailThatPassesBothNonStrictAndInStrict
        );
    });

test('validate mx record', function () {
        emailFails(
            (new Email())->validateMxRecord(),
            'plainaddress@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->validateMxRecord(),
            'plainaddress@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            (new Email())->validateMxRecord(),
            'taylor@laravel.com'
        );

        emailPasses(
            Rule::email()->validateMxRecord(),
            'taylor@laravel.com'
        );
    })->skip(! extension_loaded('intl'), 'Requires the intl extension.');

test('prevent spoofing', function () {
        emailFails(
            (new Email())->preventSpoofing(),
            'admin@examрle.com',// Contains a Cyrillic 'р' (U+0440), not a Latin 'p'
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->preventSpoofing(),
            'admin@examрle.com',// Contains a Cyrillic 'р' (U+0440), not a Latin 'p'
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';
        emailFails(
            (new Email())->preventSpoofing(),
            $spoofingEmail,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->preventSpoofing(),
            $spoofingEmail,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            (new Email())->preventSpoofing(),
            'admin@example.com'
        );

        emailPasses(
            Rule::email()->preventSpoofing(),
            'admin@example.com'
        );

        emailPasses(
            (new Email())->preventSpoofing(),
            'test👨‍💻@domain.com'
        );

        emailPasses(
            Rule::email()->preventSpoofing(),
            'test👨‍💻@domain.com'
        );
    });

test('with native validation', function () {
        emailFails(
            (new Email())->withNativeValidation(),
            'tést@domain.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->withNativeValidation(),
            'tést@domain.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            (new Email())->withNativeValidation(),
            'admin@example.com'
        );

        emailPasses(
            Rule::email()->withNativeValidation(),
            'admin@example.com'
        );
    });

test('with native validation allow unicode', function () {
        emailFails(
            (new Email())->withNativeValidation(allowUnicode: true),
            'invalid.@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->withNativeValidation(allowUnicode: true),
            'invalid.@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            (new Email())->withNativeValidation(allowUnicode: true),
            'tést@domain.com'
        );

        emailPasses(
            Rule::email()->withNativeValidation(allowUnicode: true),
            'tést@domain.com'
        );

        emailPasses(
            (new Email())->withNativeValidation(allowUnicode: true),
            'admin@example.com'
        );

        emailPasses(
            Rule::email()->withNativeValidation(allowUnicode: true),
            'admin@example.com'
        );
    });

test('rfc compliant non strict', function () {
        emailFails(
            (new Email())->rfcCompliant(),
            'invalid.@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(),
            'invalid.@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            (new Email())->rfcCompliant(),
            'test👨‍💻@domain.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(),
            'test👨‍💻@domain.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            (new Email())->rfcCompliant(),
            'admin@example.com'
        );

        emailPasses(
            Rule::email()->rfcCompliant(),
            'admin@example.com'
        );

        emailPasses(
            (new Email())->rfcCompliant(),
            'tést@domain.com'
        );

        emailPasses(
            Rule::email()->rfcCompliant(),
            'tést@domain.com'
        );
    });

test('emails that pass on rfc compliant but fail on strict', function ($email) {
        emailPasses(
            Rule::email()->rfcCompliant(),
            $email
        );

        emailFails(
            Rule::email()->rfcCompliant(strict: true),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    })->with([
    ['"has space"@example.com'],
    ['some(comment)@example.com'],
    ['abc."test"@example.com'],
    ['"escaped\\\"quote"@example.com'],
    ['test@example'],
    ['test@localhost'],
    ['name@[127.0.0.1]'],
    ['user@[IPv6:::1]'],
    ['a@[IPv6:2001:db8::1]'],
    ['user@[IPv6:::]'],
    ['"ab\\(c"@example.com'],
]);

test('emails that pass on both rfc compliant and strict', function ($email) {
        emailPasses(
            Rule::email()->rfcCompliant(),
            $email
        );

        emailPasses(
            Rule::email()->rfcCompliant(strict: true),
            $email
        );
    })->with([
    ['plainaddress@example.com'],
    ['joe.smith@example.io'],
    ['custom-tag+dev@example.org'],
    ['hyphens--@example.org'],
    ['underscore_name@example.co.uk'],
    ['underscores__@example.org'],
    ['user@subdomain.example.com'],
    ['numbers123@domain.com'],
    ['john-doe@some-domain.com'],
    ['UPPERlower@example.org'],
    ['dots.ok@sub.domain.io'],
    ['some_email+tag@domain.dev'],
    ['a@b.c'],
    ['user@xn--bcher-kva.example'],
    ['user@bücher.example'],
]);

test('emails that fail on both rfc compliant and strict', function ($email) {
        emailFails(
            Rule::email()->rfcCompliant(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(strict: true),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    })->with([
    ['invalid.@example.com'],
    ['invalid@.example.com'],
    ['.invalid@example.com'],
    ['invalid@example.com.'],
    ['some..dots@example.com'],
    ['username@sub..example.com'],
    ['test@example..com'],
    ['test@@example.com'],
    ['test👨‍💻@domain.com'],
    ['username@domain-with-hyphen-.com'],
    ['()<>[]:,;@example.com'],
    ['@example.com'],
    ['[test]@example.com'],
    ['user@example.com:3000'],
    ['"unescaped"quote@example.com'],
    ['https://example.com'],
    ['with\\escape@example.com'],
]);

test('emails that pass on both rfc compliant and rfc compliant strict', function ($email) {
        emailPasses(
            Rule::email()->rfcCompliant(),
            $email
        );

        emailPasses(
            Rule::email()->rfcCompliant(strict: true),
            $email
        );
    })->with([
    ['plainaddress@example.com'],
    ['joe.smith@example.io'],
    ['custom-tag+dev@example.org'],
    ['hyphens--@example.org'],
    ['underscore_name@example.co.uk'],
    ['underscores__@example.org'],
    ['user@subdomain.example.com'],
    ['numbers123@domain.com'],
    ['john-doe@some-domain.com'],
    ['UPPERlower@example.org'],
    ['dots.ok@sub.domain.io'],
    ['some_email+tag@domain.dev'],
    ['a@b.c'],
    ['user@xn--bcher-kva.example'],
    ['user@bücher.example'],
]);

test('emails that fail with native validation ascii pass unicode', function ($email) {
        emailFails(
            Rule::email()->withNativeValidation(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            Rule::email()->withNativeValidation(allowUnicode: true),
            $email
        );
    })->with([
    ['déjà@example.com'],
    ['测试@example.com'],
]);

test('emails that fail on both with native validation ascii and unicode', function ($email) {
        emailFails(
            Rule::email()->withNativeValidation(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->withNativeValidation(allowUnicode: true),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    })->with([
    ['test@üñîçødé.com'],
    ['user@domain..com'],
    ['test@.example.com'],
    ['username@domain-with-hyphen-.com'],
    ['пример@пример.рф'],
    ['例子@例子.公司'],
    ['name@123.123.123.123'],
]);

test('emails that pass both with native validation ascii and unicode', function ($email) {
        emailPasses(
            Rule::email()->withNativeValidation(),
            $email
        );

        emailPasses(
            Rule::email()->withNativeValidation(allowUnicode: true),
            $email
        );
    })->with([
    ['user@example.com'],
    ['user.name+tag@example.co.uk'],
    ['joe_smith@example.org'],
    ['user@[IPv6:2001:db8:1ff::a0b:dbd0]'],
    ['test@xn--bcher-kva.com'],
]);

test('emails that fail with native validation ascii pass rfc compliant', function ($email) {
        emailFails(
            Rule::email()->withNativeValidation(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            Rule::email()->rfcCompliant(),
            $email
        );
    })->with([
    ['some(comment)@example.com'],
    ['tést@example.com'],
    ['user@üñîçødé.com'],
    ['user@bücher.example'],
    ['"has space"@example.com'],
    ['"escaped\\\"quote"@example.com'],
    ['test@localhost'],
    ['test@example'],
    ['пример@пример.рф'],
    ['例子@例子.公司'],
    ['name@123.123.123.123'],
]);

test('emails that pass with native validation and rfc compliant', function ($email) {
        emailPasses(
            Rule::email()->withNativeValidation(),
            $email
        );

        emailPasses(
            Rule::email()->rfcCompliant(),
            $email
        );
    })->with([
    ['plainaddress@example.com'],
    ['joe.smith@example.io'],
    ['custom-tag+dev@example.org'],
    ['hyphens--@example.org'],
    ['underscore_name@example.co.uk'],
    ['underscores__@example.org'],
    ['user@subdomain.example.com'],
    ['numbers123@domain.com'],
    ['john-doe@some-domain.com'],
    ['UPPERlower@example.org'],
    ['dots.ok@sub.domain.io'],
    ['some_email+tag@domain.dev'],
    ['a@b.c'],
    ['user@xn--bcher-kva.example'],
    ['user_name+tag@example.io'],
    ['UPPERCASE@EXAMPLE.IO'],
    ['abc."test"@example.com'],
    ['name@[127.0.0.1]'],
    ['user@[IPv6:::1]'],
    ['a@[IPv6:2001:db8::1]'],
    ['user@[IPv6:2001:db8:1ff::a0b:dbd0]'],
]);

test('emails that fail with native validation and rfc compliant', function ($email) {
        emailFails(
            Rule::email()->withNativeValidation(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    })->with([
    ['test@@example.com'],
    ['user@domain..com'],
    ['.leadingdot@example.com'],
    ['with\\escape@example.com'],
    ['@example.com'],
    ['some)@example.com'],
    [' space@domain.com'],
    ['user@domain:port.com'],
    ['username@domain-with-hyphen-.com'],
]);

test('native validation vs rfc compliant', function () {
        $emailsThatPassNativeFailRfc = [
            // none I could find
        ];

        foreach ($emailsThatPassNativeFailRfc as $email) {
            emailPasses(
                Rule::email()->withNativeValidation(),
                $email
            );

            emailFails(
                Rule::email()->rfcCompliant(),
                $email,
                ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
            );
        }
    });

test('emails that pass native validation fail rfc compliant strict', function ($email) {
        emailPasses(
            Rule::email()->withNativeValidation(),
            $email
        );

        emailFails(
            Rule::email()->rfcCompliant(true),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    })->with([
    ['abc."test"@example.com'],
    ['name@[127.0.0.1]'],
    ['user@[IPv6:2001:db8::1]'],
    ['user@[IPv6:2001:db8:1ff::a0b:dbd0]'],
    ['"ab\\(c"@example.com'],
]);

test('emails that fail native validation pass rfc compliant strict', function ($email) {
        emailFails(
            Rule::email()->withNativeValidation(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            Rule::email()->rfcCompliant(true),
            $email
        );
    })->with([
    ['пример@пример.рф'],
    ['例子@例子.公司'],
    ['name@123.123.123.123'],
]);

test('emails that pass both native validation and rfc compliant strict', function ($email) {
        emailPasses(
            Rule::email()->withNativeValidation(),
            $email
        );

        emailPasses(
            Rule::email()->rfcCompliant(true),
            $email
        );
    })->with([
    ['user@example.com'],
    ['joe.smith+dev@example.co.uk'],
    ['user!#$%&\'*+/=?^_`{|}~@example.com'],
]);

test('emails that fail both native validation and rfc compliant strict', function ($email) {
        emailFails(
            Rule::email()->withNativeValidation(),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(true),
            $email,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    })->with([
    ['test@@example.com'],
    ['.leadingdot@example.com'],
    ['user@domain..com'],
    ['test@'],
    ['abc"quote@example.com'],
    ['some(comment)@example.com'],
    ['"has space"@example.com'],
    ['user@domain(comment)'],
    ['user@[127.0.0.1(comment)]'],
    ['some((double))comment@example.com'],
    ['"test\\\"quote"@example.com'],
    ['" leading.space"@example.com'],
]);

test('combining rules', function () {
        emailPasses(
            (new Email())->rfcCompliant(strict: true)->preventSpoofing(),
            'test@example.com'
        );

        emailPasses(
            Rule::email()->rfcCompliant(strict: true)->preventSpoofing(),
            'test@example.com'
        );

        emailFails(
            (new Email())->rfcCompliant(strict: true)->preventSpoofing()->validateMxRecord(),
            'test@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant(strict: true)->preventSpoofing()->validateMxRecord(),
            'test@example.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailPasses(
            (new Email())->preventSpoofing(),
            'test👨‍💻@domain.com'
        );

        emailPasses(
            Rule::email()->preventSpoofing(),
            'test👨‍💻@domain.com'
        );

        emailFails(
            (new Email())->preventSpoofing()->rfcCompliant(),
            'test👨‍💻@domain.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->preventSpoofing()->rfcCompliant(),
            'test👨‍💻@domain.com',
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';

        emailPasses(
            (new Email())->rfcCompliant(),
            $spoofingEmail
        );

        emailPasses(
            Rule::email()->rfcCompliant(),
            $spoofingEmail
        );

        emailFails(
            (new Email())->rfcCompliant()->preventSpoofing(),
            $spoofingEmail,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            Rule::email()->rfcCompliant()->preventSpoofing(),
            $spoofingEmail,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    })->skip(! extension_loaded('intl'), 'Requires the intl extension.');

test('macro', function () {
        Email::macro('laravelEmployee', function () {
            return static::default()->rules('ends_with:@laravel.com');
        });

        emailFails(
            Email::laravelEmployee(),
            'taylor@example.com',
            ['validation.ends_with']
        );

        emailFails(
            Rule::email()->laravelEmployee(),
            'taylor@example.com',
            ['validation.ends_with']
        );

        emailPasses(
            Email::laravelEmployee(),
            'taylor@laravel.com'
        );

        emailPasses(
            Rule::email()->laravelEmployee(),
            'taylor@laravel.com'
        );
    });

test('it can set default using', function () {
        expect(Email::default())->toBeInstanceOf(Email::class);

        $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';

        emailPasses(
            Email::default(),
            $spoofingEmail
        );

        Email::defaults(function () {
            return (new Email())->preventSpoofing();
        });

        emailFails(
            Email::default(),
            $spoofingEmail,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        Email::defaults(function () {
            return Rule::email()->rfcCompliant();
        });

        emailPasses(
            Email::default(),
            $spoofingEmail
        );

        Email::defaults(function () {
            return Rule::email()->preventSpoofing();
        });

        emailFails(
            Email::default(),
            $spoofingEmail,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    });

test('validation messages', function () {
        Email::defaults(function () {
            return Rule::email()->preventSpoofing();
        });

        $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';

        emailFails(
            Email::default(),
            $spoofingEmail,
            ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );

        emailFails(
            rule: Email::default(),
            values: $spoofingEmail,
            expectedMessages: ['The '.EMAIL_ATTRIBUTE_REPLACED.' must be a valid email address.'],
            customValidationMessage: 'The :attribute must be a valid email address.'
        );

        emailFails(
            rule: Email::default(),
            values: $spoofingEmail,
            expectedMessages: ['Please check the entered '.EMAIL_ATTRIBUTE_REPLACED.", it must be a valid email address, {$spoofingEmail} given."],
            customValidationMessage: 'Please check the entered :attribute, it must be a valid email address, :input given.'
        );

        emailFails(
            rule: Email::default(),
            values: $spoofingEmail,
            expectedMessages: ['Plain text value'],
            customValidationMessage: 'Plain text value'
        );
    });

