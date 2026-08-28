export type AuthFormContext = 'login' | 'register' | 'forgot_password' | 'reset_password' | 'password_update';

const SHARED_FIELD_MESSAGES: Record<string, Record<string, string>> = {
  email: {
    'The email field is required.': 'E-posta adresi zorunludur.',
    'The email field must be a valid email address.': 'Geçerli bir e-posta adresi girin.',
    'The email has already been taken.': 'Bu e-posta adresi zaten kayıtlı.',
    'The password reset token is invalid or has expired.': 'Şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş.',
  },
  password: {
    'The password field is required.': 'Şifre zorunludur.',
    'The password field must be at least 8 characters.': 'Şifre en az 8 karakter olmalıdır.',
    'The password field confirmation does not match.': 'Şifre onayı eşleşmiyor.',
  },
  password_confirmation: {
    'The password confirmation field is required.': 'Şifre onayı zorunludur.',
  },
  name: {
    'The name field is required.': 'Ad soyad zorunludur.',
  },
  company_name: {
    'The company name field is required.': 'Şirket adı zorunludur.',
  },
  role: {
    'The role field is required.': 'Hesap türü zorunludur.',
    'The selected role is invalid.': 'Seçilen hesap türü geçersiz.',
  },
  token: {
    'The token field is required.': 'Sıfırlama kodu zorunludur.',
  },
  current_password: {
    'The current password field is required.': 'Mevcut şifre zorunludur.',
    'The current password is incorrect.': 'Mevcut şifre yanlış.',
  },
};

const PASSWORD_UPDATE_OVERRIDES: Record<string, string> = {
  'The password field is required.': 'Yeni şifre zorunludur.',
  'The password field must be at least 8 characters.': 'Yeni şifre en az 8 karakter olmalıdır.',
  'The password field confirmation does not match.': 'Yeni şifre ile şifre onayı eşleşmiyor.',
};

const AUTH_API_MESSAGES: Record<string, string> = {
  'Validation failed.': 'Lütfen formdaki hataları düzeltin.',
  'Invalid credentials.': 'E-posta adresi veya şifre hatalı.',
  'Too many requests.': 'Çok fazla deneme yaptınız. Lütfen bir süre sonra tekrar deneyin.',
  'Unauthenticated.': 'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.',
  'If the account exists, a password reset link has been sent.':
    'Hesap mevcutsa şifre sıfırlama bağlantısı e-posta adresinize gönderildi.',
  'Password reset successful.': 'Şifreniz başarıyla sıfırlandı. Yeni şifrenizle giriş yapabilirsiniz.',
  'Password updated successfully.': 'Şifreniz güncellendi.',
};

const LOGIN_FIELD_ORDER = ['email', 'password'] as const;
const REGISTER_FIELD_ORDER = ['name', 'company_name', 'email', 'password', 'password_confirmation', 'role'] as const;
const FORGOT_PASSWORD_FIELD_ORDER = ['email'] as const;
const RESET_PASSWORD_FIELD_ORDER = ['email', 'token', 'password', 'password_confirmation'] as const;
const PASSWORD_UPDATE_FIELD_ORDER = ['current_password', 'password', 'password_confirmation'] as const;

function translatePasswordPatterns(field: string, message: string, context: AuthFormContext): string | null {
  if (field !== 'password' && field !== 'current_password') {
    return null;
  }

  const minLengthMatch = message.match(/^The password field must be at least (\d+) characters\.$/);
  if (minLengthMatch) {
    const length = minLengthMatch[1];
    if (context === 'password_update') {
      return `Yeni şifre en az ${length} karakter olmalıdır.`;
    }
    return `Şifre en az ${length} karakter olmalıdır.`;
  }

  if (message.includes('confirmation does not match')) {
    return context === 'password_update'
      ? 'Yeni şifre ile şifre onayı eşleşmiyor.'
      : 'Şifre onayı eşleşmiyor.';
  }

  if (field === 'current_password' && message.includes('incorrect')) {
    return 'Mevcut şifre yanlış.';
  }

  return null;
}

export function translateAuthFieldError(
  field: string,
  message: string,
  context: AuthFormContext = 'login',
): string {
  if (context === 'password_update' && field === 'password' && PASSWORD_UPDATE_OVERRIDES[message]) {
    return PASSWORD_UPDATE_OVERRIDES[message];
  }

  const exact = SHARED_FIELD_MESSAGES[field]?.[message];
  if (exact) {
    return exact;
  }

  return translatePasswordPatterns(field, message, context) ?? message;
}

export function translateAuthApiMessage(message: string): string {
  return AUTH_API_MESSAGES[message] ?? message;
}

export function translateUserFacingApiMessage(message: string, fallback = 'Bir hata oluştu.'): string {
  if (!message) {
    return fallback;
  }

  const translated = translateAuthApiMessage(message);
  if (translated !== message) {
    return translated;
  }

  if (message === 'Validation failed.') {
    return 'Lütfen formdaki hataları düzeltin.';
  }

  return message;
}

function resolveFirstFieldError(
  validation: Record<string, string[]>,
  fieldOrder: readonly string[],
  context: AuthFormContext,
): string | null {
  for (const field of fieldOrder) {
    const message = validation[field]?.[0];
    if (message) {
      return translateAuthFieldError(field, message, context);
    }
  }

  return null;
}

export function mapAuthFieldErrors(
  validation: Record<string, string[]>,
  fields: readonly string[],
  context: AuthFormContext,
): Record<string, string> {
  return Object.fromEntries(
    fields.map((field) => {
      const message = validation[field]?.[0];
      return [field, message ? translateAuthFieldError(field, message, context) : ''];
    }),
  );
}

function resolveAuthFormError(
  validation: Record<string, string[]>,
  fallbackMessage: string,
  fieldOrder: readonly string[],
  context: AuthFormContext,
  genericFallback: string,
): string {
  const fieldError = resolveFirstFieldError(validation, fieldOrder, context);
  if (fieldError) {
    return fieldError;
  }

  const translatedFallback = translateUserFacingApiMessage(fallbackMessage, '');
  if (translatedFallback) {
    return translatedFallback;
  }

  return genericFallback;
}

export function resolveLoginError(
  validation: Record<string, string[]>,
  fallbackMessage: string,
): string {
  return resolveAuthFormError(
    validation,
    fallbackMessage,
    LOGIN_FIELD_ORDER,
    'login',
    'Giriş başarısız. Lütfen tekrar deneyin.',
  );
}

export function resolveRegisterError(
  validation: Record<string, string[]>,
  fallbackMessage: string,
): string {
  return resolveAuthFormError(
    validation,
    fallbackMessage,
    REGISTER_FIELD_ORDER,
    'register',
    'Kayıt başarısız. Lütfen tekrar deneyin.',
  );
}

export function resolveForgotPasswordError(
  validation: Record<string, string[]>,
  fallbackMessage: string,
): string {
  return resolveAuthFormError(
    validation,
    fallbackMessage,
    FORGOT_PASSWORD_FIELD_ORDER,
    'forgot_password',
    'İşlem sırasında bir hata oluştu.',
  );
}

export function resolveResetPasswordError(
  validation: Record<string, string[]>,
  fallbackMessage: string,
): string {
  return resolveAuthFormError(
    validation,
    fallbackMessage,
    RESET_PASSWORD_FIELD_ORDER,
    'reset_password',
    'Şifre sıfırlanamadı. Lütfen tekrar deneyin.',
  );
}

export function resolvePasswordUpdateError(
  validation: Record<string, string[]>,
  fallbackMessage: string,
): string {
  return resolveAuthFormError(
    validation,
    fallbackMessage,
    PASSWORD_UPDATE_FIELD_ORDER,
    'password_update',
    'İşlem sırasında bir hata oluştu.',
  );
}

// Backward-compatible aliases used in existing tests
export const translatePasswordUpdateFieldError = (field: string, message: string): string =>
  translateAuthFieldError(field, message, 'password_update');

export const translatePasswordUpdateApiMessage = translateAuthApiMessage;

export function isNetworkError(error: unknown): boolean {
  return (
    typeof error === 'object' &&
    error !== null &&
    'isAxiosError' in error &&
    (error as { isAxiosError?: boolean; response?: unknown }).isAxiosError === true &&
    !(error as { response?: unknown }).response
  );
}

export function resolveNetworkErrorMessage(error: unknown, fallback = 'Bağlantı kurulamadı. Lütfen tekrar deneyin.'): string {
  return isNetworkError(error) ? fallback : '';
}
