module.exports = {
  testEnvironment: 'jsdom',
  testMatch: ['**/assets/js/**/__tests__/**/*.test.(js|jsx|ts|tsx)'],
  setupFilesAfterEnv: ['<rootDir>/jest.setup.js'],
  transform: {
    '^.+\\.[jt]sx?$': 'babel-jest'
  }
};
