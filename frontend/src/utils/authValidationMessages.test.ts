import { describe, expect, it } from 'vitest';
import {
  mapAuthFieldErrors,
  resolveLoginError,
  resolvePasswordUpdateError,
  resolveRegisterError,
  translateAuthApiMessage,
  translateAuthFieldError,
  translatePasswordUpdateFieldError,
  translateUserFacingApiMessage,
} from '@/utils/authValidationMessages';

describe('authValidationMessages', () => {
  describe('login', () => {
    it('translates required email and password', () => {
      expect(translateAuthFieldError('email', 'The email field is required.', 'login')).toBe(
        'E-posta adresi zorunludur.',
      );
      expect(translateAuthFieldError('password', 'The password field is required.', 'login')).toBe(
        'Şifre zorunludur.',
      );
    });

    it('translates invalid email', () => {
      expect(
        translateAuthFieldError('email', 'The email field must be a valid email address.', 'login'),
      ).toBe('Geçerli bir e-posta adresi girin.');
    });

    it('maps login field errors', () => {
      expect(
        mapAuthFieldErrors(
          { email: ['The email field is required.'], password: ['The password field is required.'] },
          ['email', 'password'],
          'login',
        ),
      ).toEqual({
        email: 'E-posta adresi zorunludur.',
        password: 'Şifre zorunludur.',
      });
    });

    it('translates invalid credentials api message', () => {
      expect(resolveLoginError({}, 'Invalid credentials.')).toBe('E-posta adresi veya şifre hatalı.');
    });

    it('prioritizes field errors over generic api message', () => {
      expect(
        resolveLoginError({ password: ['The password field is required.'] }, 'Validation failed.'),
      ).toBe('Şifre zorunludur.');
    });
  });

  describe('register', () => {
    it('translates register field errors', () => {
      expect(translateAuthFieldError('name', 'The name field is required.', 'register')).toBe(
        'Ad soyad zorunludur.',
      );
      expect(translateAuthFieldError('email', 'The email has already been taken.', 'register')).toBe(
        'Bu e-posta adresi zaten kayıtlı.',
      );
    });

    it('translates password confirmation mismatch', () => {
      expect(
        resolveRegisterError(
          { password: ['The password field confirmation does not match.'] },
          'Validation failed.',
        ),
      ).toBe('Şifre onayı eşleşmiyor.');
    });

    it('supports dynamic minimum length', () => {
      expect(
        translateAuthFieldError('password', 'The password field must be at least 12 characters.', 'register'),
      ).toBe('Şifre en az 12 karakter olmalıdır.');
    });
  });

  describe('password update (settings)', () => {
    it('translates known current_password errors', () => {
      expect(
        translatePasswordUpdateFieldError('current_password', 'The current password field is required.'),
      ).toBe('Mevcut şifre zorunludur.');
      expect(
        translatePasswordUpdateFieldError('current_password', 'The current password is incorrect.'),
      ).toBe('Mevcut şifre yanlış.');
    });

    it('uses new-password wording for password_update context', () => {
      expect(translateAuthFieldError('password', 'The password field is required.', 'password_update')).toBe(
        'Yeni şifre zorunludur.',
      );
      expect(
        translateAuthFieldError('password', 'The password field confirmation does not match.', 'password_update'),
      ).toBe('Yeni şifre ile şifre onayı eşleşmiyor.');
    });

    it('prioritizes current_password over password in resolver', () => {
      expect(
        resolvePasswordUpdateError(
          {
            current_password: ['The current password is incorrect.'],
            password: ['The password field is required.'],
          },
          'Validation failed.',
        ),
      ).toBe('Mevcut şifre yanlış.');
    });
  });

  describe('shared api messages', () => {
    it('translates validation failed', () => {
      expect(translateAuthApiMessage('Validation failed.')).toBe('Lütfen formdaki hataları düzeltin.');
      expect(translateUserFacingApiMessage('Validation failed.')).toBe('Lütfen formdaki hataları düzeltin.');
    });

    it('translates rate limit and auth messages', () => {
      expect(translateUserFacingApiMessage('Too many requests.')).toBe(
        'Çok fazla deneme yaptınız. Lütfen bir süre sonra tekrar deneyin.',
      );
      expect(translateUserFacingApiMessage('Unauthenticated.')).toBe(
        'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.',
      );
    });

    it('translates password reset success messages', () => {
      expect(
        translateAuthApiMessage('If the account exists, a password reset link has been sent.'),
      ).toBe('Hesap mevcutsa şifre sıfırlama bağlantısı e-posta adresinize gönderildi.');
      expect(translateAuthApiMessage('Password reset successful.')).toBe(
        'Şifreniz başarıyla sıfırlandı. Yeni şifrenizle giriş yapabilirsiniz.',
      );
    });

    it('falls back for unknown api errors', () => {
      expect(translateUserFacingApiMessage('Server exploded.', 'Varsayılan')).toBe('Server exploded.');
      expect(translateUserFacingApiMessage('', 'Varsayılan')).toBe('Varsayılan');
    });
  });
});
