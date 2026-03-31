/**
 * API Helper Functions for Playwright E2E Tests
 * 
 * Provides utilities for API verification and UI-to-API testing
 */

import { Page, APIRequestContext } from '@playwright/test';

export interface ApiRequest {
  url: string;
  method: string;
  status: number;
  response?: any;
  timing?: number;
}

export interface ApiVerificationResult {
  passed: boolean;
  expectedEndpoint?: string;
  actualEndpoint?: string;
  expectedMethod?: string;
  actualMethod?: string;
  error?: string;
}

/**
 * Track API requests made during a page action
 */
export async function trackApiRequests(
  page: Page,
  action: () => Promise<void>
): Promise<ApiRequest[]> {
  const requests: ApiRequest[] = [];
  
  // Set up request listener
  page.on('request', request => {
    const url = request.url();
    // Only track API requests (you may need to adjust this pattern)
    if (url.includes('/api/') || url.includes('/json')) {
      requests.push({
        url,
        method: request.method(),
        status: 0,
        timing: Date.now()
      });
    }
  });
  
  // Set up response listener
  page.on('response', response => {
    const url = response.url();
    const request = requests.find(r => r.url === url);
    if (request) {
      request.status = response.status();
      request.timing = Date.now() - request.timing!;
      
      // Try to parse JSON response
      response.json().then(data => {
        request.response = data;
      }).catch(() => {
        // Not JSON, that's fine
      });
    }
  });
  
  // Perform the action
  await action();
  
  // Wait a bit for any pending requests
  await page.waitForTimeout(1000);
  
  return requests;
}

/**
 * Verify that a specific API endpoint was called during an action
 */
export function verifyApiCall(
  requests: ApiRequest[],
  expectedUrl: string | RegExp,
  expectedMethod?: string
): ApiVerificationResult {
  const matchedRequest = requests.find(req => {
    const urlMatch = typeof expectedUrl === 'string' 
      ? req.url.includes(expectedUrl)
      : expectedUrl.test(req.url);
    
    const methodMatch = expectedMethod ? req.method === expectedMethod : true;
    
    return urlMatch && methodMatch;
  });
  
  if (!matchedRequest) {
    return {
      passed: false,
      error: `Expected API call to ${expectedUrl} not found`,
      expectedEndpoint: typeof expectedUrl === 'string' ? expectedUrl : expectedUrl.source
    };
  }
  
  return {
    passed: true,
    expectedEndpoint: typeof expectedUrl === 'string' ? expectedUrl : expectedUrl.source,
    actualEndpoint: matchedRequest.url,
    expectedMethod,
    actualMethod: matchedRequest.method
  };
}

/**
 * Make an API request directly
 */
export async function makeApiRequest(
  request: APIRequestContext,
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  url: string,
  options: {
    headers?: Record<string, string>;
    data?: any;
    expectStatus?: number;
  } = {}
): Promise<{ status: number; body: any }> {
  const { headers = {}, data, expectStatus = 200 } = options;
  
  const response = await request[method.toLowerCase()](url, {
    headers,
    data,
    failOnStatusCode: false
  });
  
  const body = await response.json().catch(() => ({}));
  
  if (response.status() !== expectStatus) {
    console.warn(`API request to ${url} returned ${response.status()}, expected ${expectStatus}`);
  }
  
  return {
    status: response.status(),
    body
  };
}

/**
 * Authenticate via API and return session/headers
 */
export async function authenticateApi(
  request: APIRequestContext,
  baseUrl: string,
  email: string,
  password: string
): Promise<{ cookies: string[]; headers: Record<string, string> }> {
  const response = await request.post(`${baseUrl}/login`, {
    data: {
      email,
      password,
      _token: await getCsrfToken(request, baseUrl)
    }
  });
  
  const setCookies = response.headers()['set-cookie'] || [];
  const cookies = Array.isArray(setCookies) ? setCookies : [setCookies];
  
  return {
    cookies,
    headers: {}
  };
}

/**
 * Get CSRF token from the page
 */
export async function getCsrfToken(request: APIRequestContext, baseUrl: string): Promise<string> {
  const response = await request.get(`${baseUrl}/`);
  const html = await response.text();
  
  // Extract CSRF token from meta tag or form
  const match = html.match(/name="_token"\s+content="([^"]+)"/) ||
                html.match(/meta name="csrf-token"\s+content="([^"]+)"/) ||
                html.match(/csrf-token"[^>]*value="([^"]+)"/);
  
  return match ? match[1] : '';
}

/**
 * Verify API response structure
 */
export function verifyResponseStructure(
  response: any,
  expectedFields: string[],
  strict: boolean = false
): { passed: boolean; missingFields: string[]; extraFields: string[] } {
  const actualFields = Object.keys(response);
  
  const missingFields = expectedFields.filter(f => !actualFields.includes(f));
  const extraFields = strict ? actualFields.filter(f => !expectedFields.includes(f)) : [];
  
  return {
    passed: missingFields.length === 0 && extraFields.length === 0,
    missingFields,
    extraFields
  };
}

/**
 * Verify database side effect after an action
 */
export async function verifyDbState(
  request: APIRequestContext,
  baseUrl: string,
  table: string,
  conditions: Record<string, any>,
  expected: boolean = true
): Promise<{ passed: boolean; error?: string }> {
  // This would typically call a test endpoint that checks DB state
  // For now, we'll make a request to check the state
  try {
    const response = await request.get(`${baseUrl}/api/test/db-check`, {
      params: { table, conditions: JSON.stringify(conditions) }
    });
    
    const data = await response.json();
    
    if (expected && !data.exists) {
      return { passed: false, error: `Expected record not found in ${table}` };
    }
    
    if (!expected && data.exists) {
      return { passed: false, error: `Unexpected record found in ${table}` };
    }
    
    return { passed: true };
  } catch (e: any) {
    return { passed: false, error: `DB check failed: ${e.message}` };
  }
}

/**
 * Test API validation errors
 */
export async function testValidationError(
  request: APIRequestContext,
  url: string,
  method: 'POST' | 'PUT' | 'PATCH',
  invalidData: Record<string, any>,
  expectedErrors: string[]
): Promise<{ passed: boolean; actualErrors: string[]; missingErrors: string[] }> {
  const response = await request[method.toLowerCase()](url, {
    data: invalidData,
    failOnStatusCode: false
  });
  
  const body = await response.json();
  
  // Extract error messages (format depends on Laravel's validation response)
  const actualErrors: string[] = [];
  if (body.errors) {
    for (const fieldErrors of Object.values(body.errors) as string[][]) {
      actualErrors.push(...fieldErrors);
    }
  } else if (body.message) {
    actualErrors.push(body.message);
  }
  
  const missingErrors = expectedErrors.filter(e => 
    !actualErrors.some(a => a.toLowerCase().includes(e.toLowerCase()))
  );
  
  return {
    passed: missingErrors.length === 0,
    actualErrors,
    missingErrors
  };
}

/**
 * Test API permission errors
 */
export async function testPermissionError(
  request: APIRequestContext,
  url: string,
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE',
  expectedStatus: number = 403
): Promise<{ passed: boolean; status: number; error?: string }> {
  const response = await request[method.toLowerCase()](url, {
    failOnStatusCode: false
  });
  
  return {
    passed: response.status() === expectedStatus,
    status: response.status(),
    error: response.status() !== expectedStatus 
      ? `Expected status ${expectedStatus}, got ${response.status()}`
      : undefined
  };
}
